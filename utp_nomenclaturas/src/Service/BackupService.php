<?php

namespace Drupal\utp_nomenclaturas\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\utp_nomenclaturas\Entity\AdSet;
use Drupal\utp_nomenclaturas\Entity\Campaign;

/**
 * Backup/restore del árbol Campaña▸Conjunto▸Anuncio — §3.5/§10 del SDD.
 *
 * `export()` emite el shape canónico (uuid-based) de este sistema, para
 * backup/restore de ida y vuelta dentro de Drupal.
 *
 * `import()` es el adaptador de migración de §10: el HTML de referencia NO
 * tiene `uuid` (solo `id` interno de localStorage) y guarda los conjuntos
 * bajo la clave `groups` (acá son `ad_sets`) y a veces la URL del anuncio
 * bajo `meta.url` (acá es un campo propio `url`). `import()` acepta AMBOS
 * shapes (legacy HTML y el canónico propio) para poder migrar un backup
 * real del HTML sin transformarlo a mano primero. Es idempotente por
 * `uuid` (§10): si el item trae `uuid` y ya existe, actualiza; si no trae
 * `uuid` (backup legacy) o el `uuid` no existe, intenta crear y usa la
 * unicidad de nombre (§3.3) para no duplicar — una colisión de nombre sin
 * `uuid` coincidente se reporta como error y se salta (no sobreescribe a
 * ciegas), en vez de abortar el import completo.
 */
class BackupService {

  private const CAMPAIGN_META_FIELDS = ['segmento', 'etapa', 'campus', 'medio', 'obj_camp', 'obj_plat', 'tipo_camp'];
  private const AD_SET_META_FIELDS = ['edad', 'ubicacion', 'facultad', 'senal', 'detalle'];
  private const AD_META_FIELDS = ['formato', 'concepto', 'motivo', 'mensaje', 'carrera', 'fecha'];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array<int, array{uuid: string, pillar_code: string, name: string,
   *   meta: array, ad_sets: array}>
   */
  public function export(): array {
    $campaignStorage = $this->entityTypeManager->getStorage('campaign');
    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    $adStorage = $this->entityTypeManager->getStorage('ad');

    $result = [];
    /** @var Campaign $campaign */
    foreach ($campaignStorage->loadMultiple() as $campaign) {
      $adSets = [];
      /** @var AdSet $adSet */
      foreach ($adSetStorage->loadByProperties(['campaign_id' => $campaign->id()]) as $adSet) {
        $ads = [];
        foreach ($adStorage->loadByProperties(['ad_set_id' => $adSet->id()]) as $ad) {
          $ads[] = [
            'uuid' => $ad->uuid(),
            'name' => $ad->get('name')->value,
            'url' => $ad->get('url')->value,
            'meta' => $this->extractFields($ad, self::AD_META_FIELDS),
          ];
        }
        $adSets[] = [
          'uuid' => $adSet->uuid(),
          'name' => $adSet->get('name')->value,
          'meta' => $this->extractFields($adSet, self::AD_SET_META_FIELDS),
          'ads' => $ads,
        ];
      }
      $result[] = [
        'uuid' => $campaign->uuid(),
        'pillar_code' => $campaign->get('pillar_code')->value,
        'name' => $campaign->get('name')->value,
        'meta' => $this->extractFields($campaign, self::CAMPAIGN_META_FIELDS),
        'ad_sets' => $adSets,
      ];
    }

    return $result;
  }

  /**
   * @param array $data Array de campañas (shape canónico o legacy HTML).
   *
   * @return array{created: int, updated: int, skipped: int, errors: string[]}
   */
  public function import(array $data): array {
    $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

    foreach ($data as $item) {
      if (!is_array($item)) {
        $summary['skipped']++;
        $summary['errors'][] = 'Item de campaña inválido (no es un objeto).';
        continue;
      }
      $this->importCampaign($item, $summary);
    }

    return $summary;
  }

  private function importCampaign(array $item, array &$summary): void {
    $pillarCode = trim((string) ($item['pillar_code'] ?? $item['pillar'] ?? ''));
    $name = trim((string) ($item['name'] ?? ''));
    $uuid = $item['uuid'] ?? NULL;

    if ($pillarCode === '' || $name === '') {
      $summary['skipped']++;
      $summary['errors'][] = 'Campaña sin pillar_code/name válido (uuid: ' . ($uuid ?? '—') . ').';
      return;
    }

    $storage = $this->entityTypeManager->getStorage('campaign');
    $meta = $this->metaFrom($item, self::CAMPAIGN_META_FIELDS);

    $existing = $uuid ? $this->loadByUuid('campaign', $uuid) : NULL;
    if ($existing) {
      $existing->set('pillar_code', $pillarCode)->set('name', $name);
      foreach ($meta as $field => $value) {
        $existing->set($field, $value);
      }
      $existing->save();
      $summary['updated']++;
      $campaign = $existing;
    }
    else {
      if ($this->nameExists($storage, ['pillar_code' => $pillarCode, 'name' => $name])) {
        $summary['skipped']++;
        $summary['errors'][] = "Campaña '$name' (pilar '$pillarCode') ya existe con otro uuid — se omite.";
        return;
      }
      $values = $meta + ['pillar_code' => $pillarCode, 'name' => $name];
      if ($uuid) {
        $values['uuid'] = $uuid;
      }
      $campaign = $storage->create($values);
      $campaign->save();
      $summary['created']++;
    }

    foreach (($item['ad_sets'] ?? $item['groups'] ?? []) as $adSetItem) {
      if (is_array($adSetItem)) {
        $this->importAdSet($adSetItem, $campaign, $summary);
      }
    }
  }

  private function importAdSet(array $item, Campaign $campaign, array &$summary): void {
    $name = trim((string) ($item['name'] ?? ''));
    $uuid = $item['uuid'] ?? NULL;
    if ($name === '') {
      $summary['skipped']++;
      $summary['errors'][] = "Conjunto sin name válido bajo campaña '{$campaign->get('name')->value}'.";
      return;
    }

    $storage = $this->entityTypeManager->getStorage('ad_set');
    $meta = $this->metaFrom($item, self::AD_SET_META_FIELDS);

    $existing = $uuid ? $this->loadByUuid('ad_set', $uuid) : NULL;
    if ($existing) {
      $existing->set('name', $name);
      foreach ($meta as $field => $value) {
        $existing->set($field, $value);
      }
      $existing->save();
      $summary['updated']++;
      $adSet = $existing;
    }
    else {
      if ($this->nameExists($storage, ['campaign_id' => $campaign->id(), 'name' => $name])) {
        $summary['skipped']++;
        $summary['errors'][] = "Conjunto '$name' ya existe en esta campaña con otro uuid — se omite.";
        return;
      }
      $values = $meta + ['campaign_id' => $campaign->id(), 'name' => $name];
      if ($uuid) {
        $values['uuid'] = $uuid;
      }
      $adSet = $storage->create($values);
      $adSet->save();
      $summary['created']++;
    }

    foreach ($item['ads'] ?? [] as $adItem) {
      if (is_array($adItem)) {
        $this->importAd($adItem, $adSet, $summary);
      }
    }
  }

  private function importAd(array $item, AdSet $adSet, array &$summary): void {
    $name = trim((string) ($item['name'] ?? ''));
    $uuid = $item['uuid'] ?? NULL;
    if ($name === '') {
      $summary['skipped']++;
      $summary['errors'][] = "Anuncio sin name válido bajo conjunto '{$adSet->get('name')->value}'.";
      return;
    }

    // El HTML legacy guarda la URL bajo `meta.url`; el shape canónico la
    // tiene como campo propio `url`.
    $url = trim((string) ($item['url'] ?? $item['meta']['url'] ?? ''));

    $storage = $this->entityTypeManager->getStorage('ad');
    $meta = $this->metaFrom($item, self::AD_META_FIELDS);

    $existing = $uuid ? $this->loadByUuid('ad', $uuid) : NULL;
    if ($existing) {
      $existing->set('name', $name)->set('url', $url);
      foreach ($meta as $field => $value) {
        $existing->set($field, $value);
      }
      $existing->save();
      $summary['updated']++;
      return;
    }

    if ($this->nameExists($storage, ['ad_set_id' => $adSet->id(), 'name' => $name])) {
      $summary['skipped']++;
      $summary['errors'][] = "Anuncio '$name' ya existe en este conjunto con otro uuid — se omite.";
      return;
    }
    $values = $meta + ['ad_set_id' => $adSet->id(), 'name' => $name, 'url' => $url];
    if ($uuid) {
      $values['uuid'] = $uuid;
    }
    $ad = $storage->create($values);
    $ad->save();
    $summary['created']++;
  }

  private function extractFields($entity, array $fields): array {
    $meta = [];
    foreach ($fields as $field) {
      $meta[$field] = (string) ($entity->get($field)->value ?? '');
    }
    return $meta;
  }

  /**
   * Lee campos meta desde el item de import, aceptando tanto `item.meta.X`
   * (ambos shapes) como `item.X` directo (por si el JSON viene aplanado).
   */
  private function metaFrom(array $item, array $fields): array {
    $meta = [];
    foreach ($fields as $field) {
      $meta[$field] = (string) ($item['meta'][$field] ?? $item[$field] ?? '');
    }
    return $meta;
  }

  private function loadByUuid(string $entityTypeId, string $uuid) {
    $entities = $this->entityTypeManager->getStorage($entityTypeId)->loadByProperties(['uuid' => $uuid]);
    return $entities ? reset($entities) : NULL;
  }

  private function nameExists($storage, array $conditions): bool {
    $query = $storage->getQuery()->accessCheck(FALSE);
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return $query->count()->execute() > 0;
  }

}
