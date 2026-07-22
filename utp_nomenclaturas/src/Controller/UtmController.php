<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\utp_nomenclaturas\Entity\Ad;
use Drupal\utp_nomenclaturas\Entity\AdSet;
use Drupal\utp_nomenclaturas\Entity\Campaign;
use Drupal\utp_nomenclaturas\Entity\ManualUtm;
use Drupal\utp_nomenclaturas\Exception\ValidationException;
use Drupal\utp_nomenclaturas\Service\UtmDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Módulo UTM (§7.3/§3.4 del SDD): UTMs derivadas (paid), config de
 * derivación, y UTMs manuales (tráfico sin pauta, §2.1).
 */
class UtmController extends ControllerBase {

  private const UTM_CONFIG_NAME = 'utp_nomenclaturas.utm_config';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly UtmDeriver $utmDeriver,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TimeInterface $time,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('utp_nomenclaturas.utm_deriver'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
    );
  }

  // ==================================================================
  // PAID (derivadas) — GET /utms/paid
  // ==================================================================

  /**
   * Devuelve las UTMs derivadas de todos los anuncios, ya aplanadas
   * (equivalente a `flattenPaid()` del HTML, líneas 1387). El filtrado por
   * medio/pilar/búsqueda se hace en el cliente, igual que el HTML de
   * referencia — no hay parámetros de filtro server-side acá.
   */
  public function listPaid(): JsonResponse {
    $metaMode = (string) ($this->configFactory->get(self::UTM_CONFIG_NAME)->get('meta_mode') ?? 'macro');
    $defaultUrl = (string) ($this->configFactory->get(self::UTM_CONFIG_NAME)->get('default_url') ?? '');

    $campaignStorage = $this->entityTypeManager->getStorage('campaign');
    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    $adStorage = $this->entityTypeManager->getStorage('ad');

    $rows = [];
    /** @var Campaign $campaign */
    foreach ($campaignStorage->loadMultiple() as $campaign) {
      /** @var AdSet $adSet */
      foreach ($adSetStorage->loadByProperties(['campaign_id' => $campaign->id()]) as $adSet) {
        /** @var Ad $ad */
        foreach ($adStorage->loadByProperties(['ad_set_id' => $adSet->id()]) as $ad) {
          $derived = $this->utmDeriver->derive([
            'medio' => $campaign->get('medio')->value,
            'tipo_camp' => $campaign->get('tipo_camp')->value,
            'campaign_name' => $campaign->get('name')->value,
            'ad_set_name' => $adSet->get('name')->value,
            'ad_name' => $ad->get('name')->value,
            'ad_url' => $ad->get('url')->value,
            'meta_mode' => $metaMode,
            'default_url' => $defaultUrl,
          ]);
          if ($derived === NULL) {
            continue;
          }
          $rows[] = $derived + [
            'ad_uuid' => $ad->uuid(),
            'ad_set_uuid' => $adSet->uuid(),
            'campaign_uuid' => $campaign->uuid(),
            'campaign_name' => $campaign->get('name')->value,
            'ad_set_name' => $adSet->get('name')->value,
            'ad_name' => $ad->get('name')->value,
            'pillar_code' => $campaign->get('pillar_code')->value,
          ];
        }
      }
    }

    return new JsonResponse($rows);
  }

  // ==================================================================
  // CONFIG — GET/PATCH /utms/config
  // ==================================================================

  public function getUtmConfig(): JsonResponse {
    $config = $this->configFactory->get(self::UTM_CONFIG_NAME);
    return new JsonResponse([
      'default_url' => (string) ($config->get('default_url') ?? ''),
      'meta_mode' => (string) ($config->get('meta_mode') ?? 'macro'),
    ]);
  }

  public function updateUtmConfig(Request $request): JsonResponse {
    $payload = $this->decode($request);
    $metaMode = (string) ($payload['meta_mode'] ?? 'macro');
    if (!in_array($metaMode, ['macro', 'hard'], TRUE)) {
      throw new ValidationException("meta_mode debe ser 'macro' o 'hard'.", [
        ['field' => 'meta_mode', 'reason' => "Valor '$metaMode' inválido."],
      ]);
    }

    $config = $this->configFactory->getEditable(self::UTM_CONFIG_NAME);
    $config->set('default_url', (string) ($payload['default_url'] ?? ''));
    $config->set('meta_mode', $metaMode);
    $config->save();

    return new JsonResponse([
      'default_url' => $config->get('default_url'),
      'meta_mode' => $config->get('meta_mode'),
    ]);
  }

  // ==================================================================
  // MANUAL — GET/POST /utms/manual, DELETE /utms/manual/{uuid}
  // ==================================================================

  public function listManual(): JsonResponse {
    $storage = $this->entityTypeManager->getStorage('manual_utm');
    $rows = array_map(
      fn (ManualUtm $utm) => $this->manualUtmToArray($utm),
      array_values($storage->loadMultiple())
    );
    // Más reciente primero, igual que `utmStore.unshift()` en el HTML.
    usort($rows, fn (array $a, array $b) => $b['id'] <=> $a['id']);

    return new JsonResponse($rows);
  }

  /**
   * @param array $payload {utm_source, utm_medium, utm_campaign?, utm_term?,
   *   utm_content?, url, qs} — el cliente ya aplicó `clean()` antes de
   *   enviar (§3.4: "limpieza opcional"); el servidor solo valida los
   *   campos obligatorios (§3.3: source+medium+url+qs).
   */
  public function createManual(Request $request): JsonResponse {
    $payload = $this->decode($request);
    $violations = [];
    foreach (['utm_source', 'utm_medium', 'url', 'qs'] as $field) {
      if (trim((string) ($payload[$field] ?? '')) === '') {
        $violations[] = ['field' => $field, 'reason' => 'Requerido'];
      }
    }
    if ($violations) {
      throw new ValidationException('source, medium, URL y query string son obligatorios.', $violations);
    }

    $utm = $this->entityTypeManager->getStorage('manual_utm')->create([
      'utm_source' => trim((string) $payload['utm_source']),
      'utm_medium' => trim((string) $payload['utm_medium']),
      'utm_campaign' => trim((string) ($payload['utm_campaign'] ?? '')),
      'utm_term' => trim((string) ($payload['utm_term'] ?? '')),
      'utm_content' => trim((string) ($payload['utm_content'] ?? '')),
      'url' => trim((string) $payload['url']),
      'qs' => (string) $payload['qs'],
      'uid' => $this->currentUser()->id(),
      'created' => $this->time->getRequestTime(),
    ]);
    $utm->save();

    return new JsonResponse($this->manualUtmToArray($utm), 201);
  }

  public function deleteManual(string $uuid): JsonResponse {
    $entities = $this->entityTypeManager->getStorage('manual_utm')->loadByProperties(['uuid' => $uuid]);
    $utm = $entities ? reset($entities) : NULL;
    if (!$utm) {
      return new JsonResponse(['error' => 'No encontrado.', 'code' => 'NOT_FOUND', 'details' => []], 404);
    }
    $utm->delete();
    return new JsonResponse(NULL, 204);
  }

  // ==================================================================
  // Helpers
  // ==================================================================

  private function decode(Request $request): array {
    $content = $request->getContent();
    if ($content === '') {
      return [];
    }
    return json_decode($content, TRUE) ?? [];
  }

  private function manualUtmToArray(ManualUtm $utm): array {
    return [
      'id' => (int) $utm->id(),
      'uuid' => $utm->uuid(),
      'utm_source' => $utm->get('utm_source')->value,
      'utm_medium' => $utm->get('utm_medium')->value,
      'utm_campaign' => $utm->get('utm_campaign')->value,
      'utm_term' => $utm->get('utm_term')->value,
      'utm_content' => $utm->get('utm_content')->value,
      'url' => $utm->get('url')->value,
      'qs' => $utm->get('qs')->value,
      'created' => (int) $utm->get('created')->value,
    ];
  }

}
