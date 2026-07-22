<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\utp_nomenclaturas\Entity\Ad;
use Drupal\utp_nomenclaturas\Entity\AdSet;
use Drupal\utp_nomenclaturas\Entity\Campaign;
use Drupal\utp_nomenclaturas\Entity\ManualUtm;
use Drupal\utp_nomenclaturas\Exception\ValidationException;
use Drupal\utp_nomenclaturas\Service\BackupService;
use Drupal\utp_nomenclaturas\Service\ExcelExporter;
use Drupal\utp_nomenclaturas\Service\UtmDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export/import (§3.5/§9.4/§10 del SDD): Excel de nomenclaturas, Excel de
 * UTMs, backup/restore JSON del árbol.
 */
class ExportController extends ControllerBase {

  private const UTM_CONFIG_NAME = 'utp_nomenclaturas.utm_config';
  private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly UtmDeriver $utmDeriver,
    protected readonly ExcelExporter $excelExporter,
    protected readonly BackupService $backupService,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('utp_nomenclaturas.utm_deriver'),
      $container->get('utp_nomenclaturas.excel_exporter'),
      $container->get('utp_nomenclaturas.backup_service'),
      $container->get('config.factory'),
    );
  }

  /**
   * GET /export/campaigns.xlsx?pillar=&medio=&q=&uuids[]=...
   *
   * Mismos filtros que TreeController::listCampaigns; `uuids[]` (si viene)
   * fija la selección explícita y los demás filtros se ignoran — igual que
   * `exportSelected` sobre `getExportFiltered()` en el HTML de referencia.
   */
  public function exportCampaigns(Request $request): Response {
    $campaigns = $this->loadFilteredCampaigns($request);
    if (!$campaigns) {
      throw new ValidationException('Selecciona al menos una campaña o ajusta los filtros.', []);
    }

    [$metaMode, $defaultUrl] = $this->utmConfigValues();
    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    $adStorage = $this->entityTypeManager->getStorage('ad');

    $tree = [];
    foreach ($campaigns as $campaign) {
      $adSets = [];
      foreach ($adSetStorage->loadByProperties(['campaign_id' => $campaign->id()]) as $adSet) {
        $ads = [];
        foreach ($adStorage->loadByProperties(['ad_set_id' => $adSet->id()]) as $ad) {
          $ads[] = ['name' => $ad->get('name')->value, 'url' => $ad->get('url')->value];
        }
        $adSets[] = ['name' => $adSet->get('name')->value, 'ads' => $ads];
      }
      $tree[] = [
        'name' => $campaign->get('name')->value,
        'medio' => $campaign->get('medio')->value,
        'tipo_camp' => $campaign->get('tipo_camp')->value,
        'ad_sets' => $adSets,
      ];
    }

    $workbook = $this->excelExporter->buildCampaignsWorkbook($tree, $metaMode, $defaultUrl);
    if (!$workbook->getSheetCount()) {
      throw new ValidationException('Las campañas seleccionadas no tienen plataforma asignada.', []);
    }

    return $this->xlsxResponse($workbook, 'UTP_Nomenclaturas_' . date('Y-m-d') . '.xlsx');
  }

  /**
   * GET /export/utms.xlsx — todas las UTMs derivadas + manuales, sin filtro
   * (igual que exportUtmExcel() en el HTML de referencia).
   */
  public function exportUtms(): Response {
    [$metaMode, $defaultUrl] = $this->utmConfigValues();

    $campaignStorage = $this->entityTypeManager->getStorage('campaign');
    $adSetStorage = $this->entityTypeManager->getStorage('ad_set');
    $adStorage = $this->entityTypeManager->getStorage('ad');

    $paidRows = [];
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
          if ($derived !== NULL) {
            $paidRows[] = $derived + [
              'campaign_name' => $campaign->get('name')->value,
              'ad_set_name' => $adSet->get('name')->value,
              'ad_name' => $ad->get('name')->value,
            ];
          }
        }
      }
    }

    $manualUtms = array_map(
      fn (ManualUtm $utm) => [
        'utm_source' => $utm->get('utm_source')->value,
        'utm_medium' => $utm->get('utm_medium')->value,
        'utm_campaign' => $utm->get('utm_campaign')->value,
        'utm_term' => $utm->get('utm_term')->value,
        'utm_content' => $utm->get('utm_content')->value,
        'url' => $utm->get('url')->value,
        'qs' => $utm->get('qs')->value,
      ],
      array_values($this->entityTypeManager->getStorage('manual_utm')->loadMultiple())
    );

    if (!$paidRows && !$manualUtms) {
      throw new ValidationException('No hay UTMs para exportar.', []);
    }

    $workbook = $this->excelExporter->buildUtmsWorkbook($paidRows, $manualUtms);
    return $this->xlsxResponse($workbook, 'UTP_UTMs_' . date('Y-m-d') . '.xlsx');
  }

  /**
   * GET /export/backup.json — backup completo del árbol (§10).
   */
  public function exportBackup(): JsonResponse {
    $response = new JsonResponse($this->backupService->export());
    $response->headers->set('Content-Disposition', 'attachment; filename="UTP_nomenclaturas_backup.json"');
    return $response;
  }

  /**
   * POST /import — restaura un backup (§10). Body: array de campañas
   * (shape canónico o legacy HTML, ver BackupService).
   */
  public function importBackup(Request $request): JsonResponse {
    $content = $request->getContent();
    $data = $content === '' ? [] : (json_decode($content, TRUE) ?? []);
    if (!is_array($data)) {
      throw new ValidationException('El backup debe ser un array de campañas.', []);
    }

    return new JsonResponse($this->backupService->import($data));
  }

  // ==================================================================
  // Helpers
  // ==================================================================

  /**
   * @return Campaign[]
   */
  private function loadFilteredCampaigns(Request $request): array {
    $storage = $this->entityTypeManager->getStorage('campaign');
    $uuids = $request->query->all('uuids');

    if ($uuids) {
      return array_values($storage->loadByProperties(['uuid' => $uuids]));
    }

    $query = $storage->getQuery()->accessCheck(FALSE);
    if ($pillar = $request->query->get('pillar')) {
      $query->condition('pillar_code', $pillar);
    }
    if ($medio = $request->query->get('medio')) {
      $query->condition('medio', $medio);
    }
    if ($q = $request->query->get('q')) {
      $query->condition('name', '%' . $q . '%', 'LIKE');
    }

    return array_values($storage->loadMultiple($query->execute()));
  }

  /**
   * @return array{0: string, 1: string} [meta_mode, default_url]
   */
  private function utmConfigValues(): array {
    $config = $this->configFactory->get(self::UTM_CONFIG_NAME);
    return [
      (string) ($config->get('meta_mode') ?? 'macro'),
      (string) ($config->get('default_url') ?? ''),
    ];
  }

  private function xlsxResponse($workbook, string $filename): Response {
    $response = new Response($this->excelExporter->writeToString($workbook));
    $response->headers->set('Content-Type', self::XLSX_MIME);
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

}
