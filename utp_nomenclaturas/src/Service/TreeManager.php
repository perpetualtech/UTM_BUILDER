<?php

namespace Drupal\utp_nomenclaturas\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\utp_nomenclaturas\Entity\Ad;
use Drupal\utp_nomenclaturas\Entity\AdSet;
use Drupal\utp_nomenclaturas\Entity\Campaign;
use Drupal\utp_nomenclaturas\Exception\ConditionalViolationException;
use Drupal\utp_nomenclaturas\Exception\DuplicateNameException;
use Drupal\utp_nomenclaturas\Exception\ValidationException;

/**
 * CRUD + validaciones (§3.2/§3.3) + derivación de nombres + duplicate para
 * el árbol Campaña ▸ Conjunto ▸ Anuncio (§7.2 del SDD).
 *
 * Un solo servicio para los 3 niveles (en vez de uno por nivel) porque el
 * flujo validar → derivar nombre → chequear unicidad → persistir es el
 * mismo; separarlo en 3 clases solo triplicaría boilerplate.
 */
class TreeManager {

  /**
   * Campo de entidad (snake_case) → clave de config (camelCase) para los
   * campos de Campaign condicionados por etapa (D1).
   */
  private const D1_FIELD_MAP = [
    'medio' => 'medio',
    'obj_camp' => 'objCamp',
    'obj_plat' => 'objPlat',
    'tipo_camp' => 'tipoCamp',
  ];

  private const CAMPAIGN_META_FIELDS = ['segmento', 'etapa', 'campus', 'medio', 'obj_camp', 'obj_plat', 'tipo_camp'];
  private const AD_SET_META_FIELDS = ['edad', 'ubicacion', 'facultad', 'senal', 'detalle'];
  private const AD_META_FIELDS = ['formato', 'concepto', 'motivo', 'mensaje', 'carrera', 'fecha'];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly NameBuilder $nameBuilder,
    protected readonly DictionaryProvider $dictionaryProvider,
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  // ==================================================================
  // CAMPAIGN
  // ==================================================================

  /**
   * @param array $payload {pillar_code: string, meta: array<string,string>}
   *
   * @throws ValidationException|ConditionalViolationException|DuplicateNameException
   */
  public function createCampaign(array $payload): Campaign {
    $pillarCode = trim((string) ($payload['pillar_code'] ?? ''));
    if ($pillarCode === '') {
      throw new ValidationException('pillar_code es requerido.', [
        ['field' => 'pillar_code', 'reason' => 'Requerido'],
      ]);
    }

    $meta = $this->extractMeta($payload['meta'] ?? [], self::CAMPAIGN_META_FIELDS);
    $this->validateCampaignFields($meta, $pillarCode);

    $name = $this->nameBuilder->campaignName($meta + ['pillar_code' => $pillarCode]);
    $this->assertCampaignNameUnique($pillarCode, $name);

    $campaign = $this->entityTypeManager->getStorage('campaign')->create(
      $meta + [
        'pillar_code' => $pillarCode,
        'name' => $name,
        'uid' => $this->currentUser->id(),
      ]
    );
    $campaign->save();

    return $campaign;
  }

  /**
   * @param array $payload Campos parciales: {pillar_code?, meta?: array,
   *   name?: string}. `name` es un override manual del nombre derivado —
   *   igual que "editar nombre inline" en el HTML de referencia
   *   (saveEdit()): reemplaza el string sin re-derivar desde meta. Uso
   *   exclusivo entre sí con `meta` (edición inline vs. edición de campos).
   */
  public function updateCampaign(Campaign $campaign, array $payload): Campaign {
    $pillarCode = array_key_exists('pillar_code', $payload)
      ? trim((string) $payload['pillar_code'])
      : (string) $campaign->get('pillar_code')->value;
    if ($pillarCode === '') {
      throw new ValidationException('pillar_code es requerido.', [
        ['field' => 'pillar_code', 'reason' => 'Requerido'],
      ]);
    }

    if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
      $newName = trim((string) $payload['name']);
      if ($newName !== $campaign->get('name')->value) {
        $this->assertCampaignNameUnique($pillarCode, $newName, (int) $campaign->id());
      }
      $campaign->set('pillar_code', $pillarCode);
      $campaign->set('name', $newName);
      $campaign->save();
      return $campaign;
    }

    $incomingMeta = $payload['meta'] ?? [];
    $meta = [];
    foreach (self::CAMPAIGN_META_FIELDS as $field) {
      $meta[$field] = array_key_exists($field, $incomingMeta)
        ? (string) $incomingMeta[$field]
        : (string) ($campaign->get($field)->value ?? '');
    }

    $this->validateCampaignFields($meta, $pillarCode);

    $newName = $this->nameBuilder->campaignName($meta + ['pillar_code' => $pillarCode]);
    if ($newName !== $campaign->get('name')->value) {
      $this->assertCampaignNameUnique($pillarCode, $newName, (int) $campaign->id());
    }

    $campaign->set('pillar_code', $pillarCode);
    $campaign->set('name', $newName);
    foreach ($meta as $field => $value) {
      $campaign->set($field, $value);
    }
    $campaign->save();

    return $campaign;
  }

  /**
   * Cascada (Campaign → AdSet → Ad) la hace Drupal solo (ON_DELETE_CASCADE).
   */
  public function deleteCampaign(Campaign $campaign): void {
    $campaign->delete();
  }

  public function duplicateCampaign(Campaign $original): Campaign {
    $pillarCode = $original->get('pillar_code')->value;
    // El HTML de referencia duplica reusando el mismo nombre y le pide al
    // usuario editarlo después ("Campaña duplicada (edita el nombre)").
    // Acá el nombre SÍ es único por constraint de DB (§3.3), así que se
    // desambigua con un sufijo hasta encontrar uno libre.
    $name = $this->resolveAvailableName(
      $original->get('name')->value,
      fn (string $candidate) => $this->campaignNameExists($pillarCode, $candidate)
    );

    $copy = $this->entityTypeManager->getStorage('campaign')->create(
      $this->extractMeta([], self::CAMPAIGN_META_FIELDS, $original) + [
        'pillar_code' => $pillarCode,
        'name' => $name,
        'uid' => $original->get('uid')->target_id,
      ]
    );
    $copy->save();

    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    foreach ($adSetStorage->loadByProperties(['campaign_id' => $original->id()]) as $originalAdSet) {
      $this->duplicateAdSet($originalAdSet, $copy);
    }

    return $copy;
  }

  private function validateCampaignFields(array $meta, string $pillarCode): void {
    // D2: segmento es requerido para poder validar el pilar contra él.
    if ($meta['segmento'] === '') {
      throw new ConditionalViolationException('D2', 'segmento es requerido para validar el pilar.', [
        ['field' => 'segmento', 'reason' => 'Requerido'],
      ]);
    }
    if (!$this->dictionaryProvider->codeExistsInList('segmento', $meta['segmento'])) {
      throw new ValidationException('Código inexistente en el diccionario.', [
        ['field' => 'segmento', 'reason' => "Valor '{$meta['segmento']}' no existe en la lista 'segmento'."],
      ]);
    }
    if (!$this->dictionaryProvider->isValidPilarSegmentoCombination($pillarCode, $meta['segmento'])) {
      throw new ConditionalViolationException('D2',
        "El pilar '$pillarCode' no es válido para segmento='{$meta['segmento']}'.",
        [['field' => 'pillar_code', 'reason' => "No compatible con segmento='{$meta['segmento']}'."]]
      );
    }

    foreach (['etapa', 'campus'] as $field) {
      if ($meta[$field] !== '' && !$this->dictionaryProvider->codeExistsInList($field, $meta[$field])) {
        throw new ValidationException('Código inexistente en el diccionario.', [
          ['field' => $field, 'reason' => "Valor '{$meta[$field]}' no existe en la lista '$field'."],
        ]);
      }
    }

    // D1: medio/obj_camp/obj_plat/tipo_camp dependen de etapa.
    foreach (self::D1_FIELD_MAP as $entityField => $configField) {
      $value = $meta[$entityField];
      if ($value === '') {
        continue;
      }
      if ($meta['etapa'] === '') {
        throw new ConditionalViolationException('D1',
          "'$entityField' requiere una etapa seleccionada.",
          [['field' => $entityField, 'reason' => 'etapa no seleccionada']]
        );
      }
      $allowed = $this->dictionaryProvider->getOptionsForEtapa($meta['etapa'], $configField);
      if (!in_array($value, $allowed, TRUE)) {
        throw new ConditionalViolationException('D1',
          "'$entityField'='$value' no es válido para etapa='{$meta['etapa']}'.",
          [['field' => $entityField, 'reason' => "Valor '$value' no permitido para etapa='{$meta['etapa']}'."]]
        );
      }
    }
  }

  private function assertCampaignNameUnique(string $pillarCode, string $name, ?int $excludeId = NULL): void {
    if ($this->campaignNameExists($pillarCode, $name, $excludeId)) {
      throw new DuplicateNameException('campaign', $name, "pilar '$pillarCode'");
    }
  }

  private function campaignNameExists(string $pillarCode, string $name, ?int $excludeId = NULL): bool {
    $query = $this->entityTypeManager->getStorage('campaign')->getQuery()
      ->accessCheck(FALSE)
      ->condition('pillar_code', $pillarCode)
      ->condition('name', $name);
    if ($excludeId !== NULL) {
      $query->condition('id', $excludeId, '<>');
    }
    return $query->count()->execute() > 0;
  }

  // ==================================================================
  // AD SET
  // ==================================================================

  /**
   * @param array $payload {meta: array<string,string>, weight?: int}
   */
  public function createAdSet(Campaign $campaign, array $payload): AdSet {
    $meta = $this->extractMeta($payload['meta'] ?? [], self::AD_SET_META_FIELDS);
    $this->validateAdSetFields($meta);

    $name = $this->nameBuilder->adSetName($meta);
    $this->assertAdSetNameUnique((int) $campaign->id(), $name);

    $adSet = $this->entityTypeManager->getStorage('ad_set')->create(
      $meta + [
        'campaign_id' => $campaign->id(),
        'name' => $name,
        'weight' => $payload['weight'] ?? 0,
      ]
    );
    $adSet->save();

    return $adSet;
  }

  /**
   * @param array $payload {meta?: array, weight?: int, name?: string}.
   *   `name` es override manual (edición inline), ver nota en updateCampaign().
   */
  public function updateAdSet(AdSet $adSet, array $payload): AdSet {
    if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
      $newName = trim((string) $payload['name']);
      $campaignId = (int) $adSet->get('campaign_id')->target_id;
      if ($newName !== $adSet->get('name')->value) {
        $this->assertAdSetNameUnique($campaignId, $newName, (int) $adSet->id());
      }
      $adSet->set('name', $newName);
      $adSet->save();
      return $adSet;
    }

    $incomingMeta = $payload['meta'] ?? [];
    $meta = [];
    foreach (self::AD_SET_META_FIELDS as $field) {
      $meta[$field] = array_key_exists($field, $incomingMeta)
        ? (string) $incomingMeta[$field]
        : (string) ($adSet->get($field)->value ?? '');
    }

    $this->validateAdSetFields($meta);

    $newName = $this->nameBuilder->adSetName($meta);
    if ($newName !== $adSet->get('name')->value) {
      $this->assertAdSetNameUnique((int) $adSet->get('campaign_id')->target_id, $newName, (int) $adSet->id());
    }

    $adSet->set('name', $newName);
    foreach ($meta as $field => $value) {
      $adSet->set($field, $value);
    }
    if (array_key_exists('weight', $payload)) {
      $adSet->set('weight', $payload['weight']);
    }
    $adSet->save();

    return $adSet;
  }

  public function deleteAdSet(AdSet $adSet): void {
    $adSet->delete();
  }

  public function duplicateAdSet(AdSet $original, Campaign $targetCampaign): AdSet {
    $campaignId = (int) $targetCampaign->id();
    // Solo colisiona si se duplica como hermano bajo la MISMA campaña
    // (POST /ad-sets/{uuid}/duplicate); al duplicar en cascada desde
    // duplicateCampaign() el target ya es una campaña nueva y el nombre
    // original queda libre.
    $name = $this->resolveAvailableName(
      $original->get('name')->value,
      fn (string $candidate) => $this->adSetNameExists($campaignId, $candidate)
    );

    $copy = $this->entityTypeManager->getStorage('ad_set')->create(
      $this->extractMeta([], self::AD_SET_META_FIELDS, $original) + [
        'campaign_id' => $campaignId,
        'name' => $name,
        'weight' => $original->get('weight')->value ?? 0,
      ]
    );
    $copy->save();

    $adStorage = $this->entityTypeManager->getStorage('ad');
    foreach ($adStorage->loadByProperties(['ad_set_id' => $original->id()]) as $originalAd) {
      $this->duplicateAd($originalAd, $copy);
    }

    return $copy;
  }

  private function validateAdSetFields(array $meta): void {
    foreach (['edad', 'ubicacion', 'senal'] as $field) {
      if ($meta[$field] !== '' && !$this->dictionaryProvider->codeExistsInList($field, $meta[$field])) {
        throw new ValidationException('Código inexistente en el diccionario.', [
          ['field' => $field, 'reason' => "Valor '{$meta[$field]}' no existe en la lista '$field'."],
        ]);
      }
    }

    // `detalle` admite valor libre (§2.2) — no se valida contra el diccionario.
    if ($meta['facultad'] !== '') {
      if (!$this->dictionaryProvider->codeExistsInList('facultad', $meta['facultad'])) {
        throw new ValidationException('Código inexistente en el diccionario.', [
          ['field' => 'facultad', 'reason' => "Valor '{$meta['facultad']}' no existe en la lista 'facultad'."],
        ]);
      }
      // D3: facultad → ubicación.
      if ($meta['ubicacion'] !== '' && !$this->dictionaryProvider->isFacultadValidForUbicacion($meta['facultad'], $meta['ubicacion'])) {
        throw new ConditionalViolationException('D3',
          "facultad '{$meta['facultad']}' no está disponible para ubicacion='{$meta['ubicacion']}'.",
          [['field' => 'facultad', 'reason' => "No disponible para ubicacion='{$meta['ubicacion']}'."]]
        );
      }
    }
  }

  private function assertAdSetNameUnique(int $campaignId, string $name, ?int $excludeId = NULL): void {
    if ($this->adSetNameExists($campaignId, $name, $excludeId)) {
      throw new DuplicateNameException('ad_set', $name, "campaña #$campaignId");
    }
  }

  private function adSetNameExists(int $campaignId, string $name, ?int $excludeId = NULL): bool {
    $query = $this->entityTypeManager->getStorage('ad_set')->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', $campaignId)
      ->condition('name', $name);
    if ($excludeId !== NULL) {
      $query->condition('id', $excludeId, '<>');
    }
    return $query->count()->execute() > 0;
  }

  // ==================================================================
  // AD
  // ==================================================================

  /**
   * @param array $payload {meta: array<string,string>, url?: string, weight?: int}
   */
  public function createAd(AdSet $adSet, array $payload): Ad {
    $meta = $this->extractMeta($payload['meta'] ?? [], self::AD_META_FIELDS);
    $this->validateAdFields($meta);

    $name = $this->nameBuilder->adName($meta);
    $this->assertAdNameUnique((int) $adSet->id(), $name);

    $ad = $this->entityTypeManager->getStorage('ad')->create(
      $meta + [
        'ad_set_id' => $adSet->id(),
        'name' => $name,
        'url' => $payload['url'] ?? NULL,
        'weight' => $payload['weight'] ?? 0,
      ]
    );
    $ad->save();

    return $ad;
  }

  /**
   * @param array $payload Campos parciales: {meta?: array, url?: string,
   *   name?: string}. `name` es override manual (edición inline), ver nota
   *   en updateCampaign().
   */
  public function updateAd(Ad $ad, array $payload): Ad {
    if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
      $newName = trim((string) $payload['name']);
      $adSetId = (int) $ad->get('ad_set_id')->target_id;
      if ($newName !== $ad->get('name')->value) {
        $this->assertAdNameUnique($adSetId, $newName, (int) $ad->id());
      }
      $ad->set('name', $newName);
      if (array_key_exists('url', $payload)) {
        $ad->set('url', $payload['url']);
      }
      $ad->save();
      return $ad;
    }

    $incomingMeta = $payload['meta'] ?? [];
    $meta = [];
    foreach (self::AD_META_FIELDS as $field) {
      $meta[$field] = array_key_exists($field, $incomingMeta)
        ? (string) $incomingMeta[$field]
        : (string) ($ad->get($field)->value ?? '');
    }

    $this->validateAdFields($meta);

    $newName = $this->nameBuilder->adName($meta);
    if ($newName !== $ad->get('name')->value) {
      $this->assertAdNameUnique((int) $ad->get('ad_set_id')->target_id, $newName, (int) $ad->id());
    }

    $ad->set('name', $newName);
    foreach ($meta as $field => $value) {
      $ad->set($field, $value);
    }
    // `url` no forma parte del nombre (§2.2) — se puede actualizar sola.
    if (array_key_exists('url', $payload)) {
      $ad->set('url', $payload['url']);
    }
    $ad->save();

    return $ad;
  }

  public function deleteAd(Ad $ad): void {
    $ad->delete();
  }

  public function duplicateAd(Ad $original, AdSet $targetAdSet): Ad {
    $adSetId = (int) $targetAdSet->id();
    $name = $this->resolveAvailableName(
      $original->get('name')->value,
      fn (string $candidate) => $this->adNameExists($adSetId, $candidate)
    );

    $copy = $this->entityTypeManager->getStorage('ad')->create(
      $this->extractMeta([], self::AD_META_FIELDS, $original) + [
        'ad_set_id' => $adSetId,
        'name' => $name,
        'url' => $original->get('url')->value,
        'weight' => $original->get('weight')->value ?? 0,
      ]
    );
    $copy->save();

    return $copy;
  }

  private function validateAdFields(array $meta): void {
    foreach (['formato', 'motivo', 'carrera', 'fecha'] as $field) {
      if ($meta[$field] !== '' && !$this->dictionaryProvider->codeExistsInList($field, $meta[$field])) {
        throw new ValidationException('Código inexistente en el diccionario.', [
          ['field' => $field, 'reason' => "Valor '{$meta[$field]}' no existe en la lista '$field'."],
        ]);
      }
    }
    // `concepto` valida contra la lista 'nombre' del diccionario (§2.2/Anexo A).
    if ($meta['concepto'] !== '' && !$this->dictionaryProvider->codeExistsInList('nombre', $meta['concepto'])) {
      throw new ValidationException('Código inexistente en el diccionario.', [
        ['field' => 'concepto', 'reason' => "Valor '{$meta['concepto']}' no existe en la lista 'nombre'."],
      ]);
    }
    // `mensaje` admite valor libre (§2.2) — no se valida contra el diccionario.
  }

  private function assertAdNameUnique(int $adSetId, string $name, ?int $excludeId = NULL): void {
    if ($this->adNameExists($adSetId, $name, $excludeId)) {
      throw new DuplicateNameException('ad', $name, "conjunto #$adSetId");
    }
  }

  private function adNameExists(int $adSetId, string $name, ?int $excludeId = NULL): bool {
    $query = $this->entityTypeManager->getStorage('ad')->getQuery()
      ->accessCheck(FALSE)
      ->condition('ad_set_id', $adSetId)
      ->condition('name', $name);
    if ($excludeId !== NULL) {
      $query->condition('id', $excludeId, '<>');
    }
    return $query->count()->execute() > 0;
  }

  // ==================================================================
  // Helpers
  // ==================================================================

  /**
   * Normaliza un array de meta entrante a strings, con default '' para los
   * campos ausentes; opcionalmente toma los valores desde una entidad
   * existente en vez del array de entrada (usado en duplicate()).
   */
  private function extractMeta(array $incoming, array $fields, $sourceEntity = NULL): array {
    $meta = [];
    foreach ($fields as $field) {
      if ($sourceEntity !== NULL) {
        $meta[$field] = (string) ($sourceEntity->get($field)->value ?? '');
      }
      else {
        $meta[$field] = (string) ($incoming[$field] ?? '');
      }
    }
    return $meta;
  }

  /**
   * Encuentra un nombre libre a partir de $baseName, probando sufijos
   * "-copia", "-copia-2", ... Usado solo por duplicate*() — el HTML de
   * referencia duplica reusando el mismo nombre y confía en que el usuario
   * lo edite después; acá el nombre es único por constraint real de DB
   * (§3.3), así que hace falta desambiguar para que el duplicado se pueda
   * persistir.
   */
  private function resolveAvailableName(string $baseName, callable $exists): string {
    if (!$exists($baseName)) {
      return $baseName;
    }
    $attempt = 2;
    do {
      $candidate = $attempt === 2 ? "{$baseName}-copia" : "{$baseName}-copia-{$attempt}";
      $attempt++;
    } while ($exists($candidate));

    return $candidate;
  }

}
