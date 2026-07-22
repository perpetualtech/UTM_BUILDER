<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\utp_nomenclaturas\Entity\Ad;
use Drupal\utp_nomenclaturas\Entity\AdSet;
use Drupal\utp_nomenclaturas\Entity\Campaign;
use Drupal\utp_nomenclaturas\Service\TreeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controlador delgado del árbol Campaña▸Conjunto▸Anuncio (§7.2 del SDD).
 *
 * Solo parsea el Request y delega a TreeManager — la lógica de negocio
 * (validación, derivación de nombre, unicidad) vive ahí, no acá.
 */
class TreeController extends ControllerBase {

  public function __construct(
    protected readonly TreeManager $treeManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('utp_nomenclaturas.tree_manager'),
      $container->get('entity_type.manager'),
    );
  }

  // ==================================================================
  // CAMPAIGN
  // ==================================================================

  public function listCampaigns(Request $request): JsonResponse {
    $query = $this->entityTypeManager->getStorage('campaign')->getQuery()->accessCheck(FALSE);

    if ($pillar = $request->query->get('pillar')) {
      $query->condition('pillar_code', $pillar);
    }
    if ($medio = $request->query->get('medio')) {
      $query->condition('medio', $medio);
    }
    if ($q = $request->query->get('q')) {
      $query->condition('name', '%' . $q . '%', 'LIKE');
    }

    $storage = $this->entityTypeManager->getStorage('campaign');
    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    $adStorage = $this->entityTypeManager->getStorage('ad');

    $result = [];
    foreach ($storage->loadMultiple($query->execute()) as $campaign) {
      $adSetIds = $adSetStorage->getQuery()->accessCheck(FALSE)
        ->condition('campaign_id', $campaign->id())
        ->execute();

      // §4 del SDD: KPI de "anuncios" = Σ anuncios de todos los conjuntos
      // de la campaña, no solo el conteo de conjuntos.
      $adCount = $adSetIds
        ? (int) $adStorage->getQuery()->accessCheck(FALSE)
          ->condition('ad_set_id', $adSetIds, 'IN')
          ->count()
          ->execute()
        : 0;

      $result[] = $this->campaignToArray($campaign) + [
        'ad_sets_count' => count($adSetIds),
        'ads_count' => $adCount,
      ];
    }

    return new JsonResponse($result);
  }

  public function getCampaignTree(string $uuid, Request $request): JsonResponse {
    $campaign = $this->loadByUuid('campaign', $uuid);
    if (!$campaign) {
      return $this->notFound();
    }

    $include = (string) $request->query->get('include', '');
    $data = $this->campaignToArray($campaign);

    if (str_contains($include, 'ad_sets')) {
      $includeAds = str_contains($include, 'ads');
      $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
      $adStorage = $this->entityTypeManager->getStorage('ad');

      $data['ad_sets'] = [];
      foreach ($adSetStorage->loadByProperties(['campaign_id' => $campaign->id()]) as $adSet) {
        $adSetData = $this->adSetToArray($adSet);
        if ($includeAds) {
          $adSetData['ads'] = array_map(
            fn (Ad $ad) => $this->adToArray($ad),
            array_values($adStorage->loadByProperties(['ad_set_id' => $adSet->id()]))
          );
        }
        $data['ad_sets'][] = $adSetData;
      }
    }

    return new JsonResponse($data);
  }

  public function createCampaign(Request $request): JsonResponse {
    $campaign = $this->treeManager->createCampaign($this->decode($request));
    return new JsonResponse($this->campaignToArray($campaign), 201);
  }

  public function updateCampaign(string $uuid, Request $request): JsonResponse {
    $campaign = $this->loadByUuid('campaign', $uuid);
    if (!$campaign) {
      return $this->notFound();
    }
    $campaign = $this->treeManager->updateCampaign($campaign, $this->decode($request));
    return new JsonResponse($this->campaignToArray($campaign));
  }

  public function deleteCampaign(string $uuid): JsonResponse {
    $campaign = $this->loadByUuid('campaign', $uuid);
    if (!$campaign) {
      return $this->notFound();
    }
    $this->treeManager->deleteCampaign($campaign);
    return new JsonResponse(NULL, 204);
  }

  public function duplicateCampaign(string $uuid): JsonResponse {
    $campaign = $this->loadByUuid('campaign', $uuid);
    if (!$campaign) {
      return $this->notFound();
    }
    $copy = $this->treeManager->duplicateCampaign($campaign);
    return new JsonResponse($this->campaignToArray($copy), 201);
  }

  // ==================================================================
  // AD SET
  // ==================================================================

  public function createAdSet(string $uuid, Request $request): JsonResponse {
    $campaign = $this->loadByUuid('campaign', $uuid);
    if (!$campaign) {
      return $this->notFound();
    }
    $adSet = $this->treeManager->createAdSet($campaign, $this->decode($request));
    return new JsonResponse($this->adSetToArray($adSet), 201);
  }

  public function updateAdSet(string $uuid, Request $request): JsonResponse {
    $adSet = $this->loadByUuid('ad_set', $uuid);
    if (!$adSet) {
      return $this->notFound();
    }
    $adSet = $this->treeManager->updateAdSet($adSet, $this->decode($request));
    return new JsonResponse($this->adSetToArray($adSet));
  }

  public function deleteAdSet(string $uuid): JsonResponse {
    $adSet = $this->loadByUuid('ad_set', $uuid);
    if (!$adSet) {
      return $this->notFound();
    }
    $this->treeManager->deleteAdSet($adSet);
    return new JsonResponse(NULL, 204);
  }

  public function duplicateAdSet(string $uuid): JsonResponse {
    /** @var AdSet|null $adSet */
    $adSet = $this->loadByUuid('ad_set', $uuid);
    if (!$adSet) {
      return $this->notFound();
    }
    $campaign = $this->entityTypeManager->getStorage('campaign')->load($adSet->get('campaign_id')->target_id);
    $copy = $this->treeManager->duplicateAdSet($adSet, $campaign);
    return new JsonResponse($this->adSetToArray($copy), 201);
  }

  // ==================================================================
  // AD
  // ==================================================================

  public function createAd(string $uuid, Request $request): JsonResponse {
    $adSet = $this->loadByUuid('ad_set', $uuid);
    if (!$adSet) {
      return $this->notFound();
    }
    $ad = $this->treeManager->createAd($adSet, $this->decode($request));
    return new JsonResponse($this->adToArray($ad), 201);
  }

  public function updateAd(string $uuid, Request $request): JsonResponse {
    $ad = $this->loadByUuid('ad', $uuid);
    if (!$ad) {
      return $this->notFound();
    }
    $ad = $this->treeManager->updateAd($ad, $this->decode($request));
    return new JsonResponse($this->adToArray($ad));
  }

  public function deleteAd(string $uuid): JsonResponse {
    $ad = $this->loadByUuid('ad', $uuid);
    if (!$ad) {
      return $this->notFound();
    }
    $this->treeManager->deleteAd($ad);
    return new JsonResponse(NULL, 204);
  }

  public function duplicateAd(string $uuid): JsonResponse {
    /** @var Ad|null $ad */
    $ad = $this->loadByUuid('ad', $uuid);
    if (!$ad) {
      return $this->notFound();
    }
    $adSet = $this->entityTypeManager->getStorage('ad_set')->load($ad->get('ad_set_id')->target_id);
    $copy = $this->treeManager->duplicateAd($ad, $adSet);
    return new JsonResponse($this->adToArray($copy), 201);
  }

  // ==================================================================
  // Helpers
  // ==================================================================

  private function loadByUuid(string $entityTypeId, string $uuid) {
    $entities = $this->entityTypeManager->getStorage($entityTypeId)->loadByProperties(['uuid' => $uuid]);
    return $entities ? reset($entities) : NULL;
  }

  private function decode(Request $request): array {
    $content = $request->getContent();
    if ($content === '') {
      return [];
    }
    return json_decode($content, TRUE) ?? [];
  }

  private function notFound(): JsonResponse {
    return new JsonResponse([
      'error' => 'No encontrado.',
      'code' => 'NOT_FOUND',
      'details' => [],
    ], 404);
  }

  private function campaignToArray(Campaign $campaign): array {
    return [
      'id' => (int) $campaign->id(),
      'uuid' => $campaign->uuid(),
      'name' => $campaign->get('name')->value,
      'pillar_code' => $campaign->get('pillar_code')->value,
      'meta' => [
        'segmento' => $campaign->get('segmento')->value,
        'etapa' => $campaign->get('etapa')->value,
        'campus' => $campaign->get('campus')->value,
        'medio' => $campaign->get('medio')->value,
        'obj_camp' => $campaign->get('obj_camp')->value,
        'obj_plat' => $campaign->get('obj_plat')->value,
        'tipo_camp' => $campaign->get('tipo_camp')->value,
      ],
    ];
  }

  private function adSetToArray(AdSet $adSet): array {
    return [
      'id' => (int) $adSet->id(),
      'uuid' => $adSet->uuid(),
      'name' => $adSet->get('name')->value,
      'meta' => [
        'edad' => $adSet->get('edad')->value,
        'ubicacion' => $adSet->get('ubicacion')->value,
        'facultad' => $adSet->get('facultad')->value,
        'senal' => $adSet->get('senal')->value,
        'detalle' => $adSet->get('detalle')->value,
      ],
    ];
  }

  private function adToArray(Ad $ad): array {
    return [
      'id' => (int) $ad->id(),
      'uuid' => $ad->uuid(),
      'name' => $ad->get('name')->value,
      'url' => $ad->get('url')->value,
      'meta' => [
        'formato' => $ad->get('formato')->value,
        'concepto' => $ad->get('concepto')->value,
        'motivo' => $ad->get('motivo')->value,
        'mensaje' => $ad->get('mensaje')->value,
        'carrera' => $ad->get('carrera')->value,
        'fecha' => $ad->get('fecha')->value,
      ],
    ];
  }

}
