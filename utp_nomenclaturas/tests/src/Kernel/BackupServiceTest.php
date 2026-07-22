<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\utp_nomenclaturas\Service\BackupService;

/**
 * §10 del SDD: "Idempotente por uuid". Cubre el round-trip export→import
 * (backup propio) y la migración desde el shape legacy del HTML de
 * referencia (sin uuid, `groups` en vez de `ad_sets`, `meta.url`).
 *
 * @group utp_nomenclaturas
 */
class BackupServiceTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  protected BackupService $backupService;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('campaign');
    $this->installEntitySchema('ad_set');
    $this->installEntitySchema('ad');
    $this->installConfig(['utp_nomenclaturas']);

    $this->backupService = $this->container->get('utp_nomenclaturas.backup_service');
  }

  private function seedOneCampaignTree(): void {
    $campaignStorage = $this->container->get('entity_type.manager')->getStorage('campaign');
    $campaign = $campaignStorage->create([
      'pillar_code' => 'calidad',
      'name' => 'jovenes_upper_lima_meta_awareness_alcance_video_calidad',
      'segmento' => 'jovenes', 'etapa' => 'upper', 'campus' => 'lima',
      'medio' => 'Meta', 'obj_camp' => 'Awareness', 'obj_plat' => 'Alcance', 'tipo_camp' => 'Video',
    ]);
    $campaign->save();

    $adSetStorage = $this->container->get('entity_type.manager')->getStorage('ad_set');
    $adSet = $adSetStorage->create([
      'campaign_id' => $campaign->id(),
      'name' => 'j1_lima_ing_broad_gaming-tech',
      'edad' => 'j1', 'ubicacion' => 'lima', 'facultad' => 'ing', 'senal' => 'broad', 'detalle' => 'gaming-tech',
    ]);
    $adSet->save();

    $adStorage = $this->container->get('entity_type.manager')->getStorage('ad');
    $ad = $adStorage->create([
      'ad_set_id' => $adSet->id(),
      'name' => 'video_marca_testimonial_estudia-trabaja_no-carreras_ene26',
      'formato' => 'video', 'concepto' => 'marca', 'motivo' => 'testimonial',
      'mensaje' => 'estudia-trabaja', 'carrera' => 'no-carreras', 'fecha' => 'ene26',
      'url' => 'https://utp.edu.pe/landing',
    ]);
    $ad->save();
  }

  public function testExportProducesFullTreeWithUuids(): void {
    $this->seedOneCampaignTree();

    $export = $this->backupService->export();
    $this->assertCount(1, $export);
    $this->assertNotEmpty($export[0]['uuid']);
    $this->assertSame('calidad', $export[0]['pillar_code']);
    $this->assertCount(1, $export[0]['ad_sets']);
    $this->assertCount(1, $export[0]['ad_sets'][0]['ads']);
    $this->assertSame('https://utp.edu.pe/landing', $export[0]['ad_sets'][0]['ads'][0]['url']);
  }

  public function testReimportingOwnExportIsIdempotentByUuid(): void {
    $this->seedOneCampaignTree();
    $export = $this->backupService->export();

    $summary = $this->backupService->import($export);
    $this->assertSame(0, $summary['created'], 'Ya existen por uuid — todo debe ser update, no create.');
    $this->assertSame(3, $summary['updated'], '1 campaña + 1 conjunto + 1 anuncio.');
    $this->assertSame(0, $summary['skipped']);
    $this->assertSame([], $summary['errors']);

    $this->assertCount(1, $this->backupService->export(), 'No se duplicó nada.');
  }

  public function testImportWithoutUuidCreatesNewEntities(): void {
    $data = [[
      'pillar_code' => 'orgullo',
      'name' => 'campana-sin-uuid',
      'meta' => ['segmento' => 'adultos', 'etapa' => 'upper'],
      'ad_sets' => [],
    ]];

    $summary = $this->backupService->import($data);
    $this->assertSame(1, $summary['created']);
    $this->assertCount(1, $this->backupService->export());
  }

  public function testImportWithoutUuidSkipsOnNameCollision(): void {
    $this->seedOneCampaignTree();
    $data = [[
      'pillar_code' => 'calidad',
      'name' => 'jovenes_upper_lima_meta_awareness_alcance_video_calidad',
      'meta' => ['segmento' => 'jovenes'],
      'ad_sets' => [],
    ]];

    $summary = $this->backupService->import($data);
    $this->assertSame(0, $summary['created']);
    $this->assertSame(1, $summary['skipped']);
    $this->assertNotEmpty($summary['errors']);
    $this->assertCount(1, $this->backupService->export(), 'No se creó una campaña duplicada.');
  }

  /**
   * §10: backup legacy del HTML — sin `uuid`, conjuntos bajo `groups`, y la
   * URL del anuncio bajo `meta.url` (ver BackupService::importAd()).
   */
  public function testImportLegacyHtmlShapeMapsGroupsAndMetaUrl(): void {
    $legacy = [[
      'id' => 'legacy-1',
      'pillar' => 'accesibilidad',
      'name' => 'adultos_lower_virtual_gads_conversiones_leadweb_search_accesibilidad',
      'meta' => [
        'segmento' => 'adultos', 'etapa' => 'lower', 'campus' => 'virtual',
        'medio' => 'GoogleAds', 'obj_camp' => 'Conversiones', 'obj_plat' => 'LeadWeb', 'tipo_camp' => 'Search',
      ],
      'groups' => [
        [
          'id' => 'legacy-grp-1',
          'name' => 'a1_nacional_virtual_lal_bbdd-inscritos-total',
          'meta' => ['edad' => 'a1', 'ubicacion' => 'nacional', 'facultad' => 'virtual', 'senal' => 'lal', 'detalle' => 'bbdd-inscritos-total'],
          'ads' => [
            [
              'id' => 'legacy-ad-1',
              'name' => 'rsa_modalidad_malla_porque-utp_ingenieria_feb26',
              'meta' => [
                'formato' => 'rsa', 'concepto' => 'modalidad', 'motivo' => 'malla',
                'mensaje' => 'porque-utp', 'carrera' => 'ingenieria', 'fecha' => 'feb26',
                'url' => 'https://utp.edu.pe/ingenieria',
              ],
            ],
          ],
        ],
      ],
    ]];

    $summary = $this->backupService->import($legacy);
    $this->assertSame(3, $summary['created']);
    $this->assertSame([], $summary['errors']);

    $export = $this->backupService->export();
    $this->assertCount(1, $export);
    $this->assertNotEmpty($export[0]['uuid'], 'Se generó un uuid nuevo — el legacy no traía uno.');
    $this->assertSame('https://utp.edu.pe/ingenieria', $export[0]['ad_sets'][0]['ads'][0]['url']);
  }

}
