<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\utp_nomenclaturas\Service\DictionaryProvider;

/**
 * Acceptance criteria de la Fase 0 (§11 del SDD):
 *
 * "drush en utp_nomenclaturas sin errores; GET /config devuelve el bundle
 * completo idéntico al Anexo A" — sin Drupal real disponible en esta sesión,
 * este Kernel test es el sustituto: instala el módulo contra SQLite en
 * memoria y compara el bundle devuelto por DictionaryProvider contra el
 * Anexo A/B completo, campo por campo.
 *
 * @group utp_nomenclaturas
 */
class DictionaryBundleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('campaign');
    $this->installEntitySchema('ad_set');
    $this->installEntitySchema('ad');
    $this->installEntitySchema('manual_utm');
    $this->installConfig(['utp_nomenclaturas']);
  }

  /**
   * Las 4 content entities de Fase 0 quedan registradas y son instalables.
   */
  public function testEntitiesAreRegistered(): void {
    $entityTypeManager = $this->container->get('entity_type.manager');

    foreach (['campaign', 'ad_set', 'ad', 'manual_utm'] as $entityTypeId) {
      $definition = $entityTypeManager->getDefinition($entityTypeId);
      $this->assertNotNull($definition, "Entity type '$entityTypeId' está definido.");
      $this->assertTrue(
        $this->container->get('entity_type.manager')->getStorage($entityTypeId) !== NULL,
        "Entity type '$entityTypeId' tiene storage instalable."
      );
    }
  }

  /**
   * El bundle de DictionaryProvider es idéntico, campo por campo, al
   * Anexo A/B del SDD (no una muestra parcial).
   */
  public function testDictionaryBundleMatchesAnexoAyB(): void {
    /** @var \Drupal\utp_nomenclaturas\Service\DictionaryProvider $provider */
    $provider = $this->container->get('utp_nomenclaturas.dictionary_provider');
    $this->assertInstanceOf(DictionaryProvider::class, $provider);

    $bundle = $provider->getBundle();

    $this->assertEquals($this->expectedLists(), $bundle['lists']);
    $this->assertEquals($this->expectedEtapaConditionals(), $bundle['etapa_conditionals']);
    $this->assertEquals($this->expectedSegmentoPilar(), $bundle['segmento_pilar']);
    $this->assertEquals($this->expectedFacultadNombre(), $bundle['facultad_nombre']);
    $this->assertEquals($this->expectedCampusFacultad(), $bundle['campus_facultad']);
    $this->assertEquals($this->expectedUbicacionGrupo(), $bundle['ubicacion_grupo']);
    $this->assertEquals($this->expectedSedesEspecificas(), $bundle['sedes_especificas']);
    $this->assertEquals($this->expectedSedeGrupo(), $bundle['sede_grupo']);
    $this->assertEquals(['Meta', 'Tiktok', 'DV360', 'LinkedIn', 'GoogleAds'], $bundle['platforms']);

    $this->assertEquals($this->expectedUtmPresets(), $bundle['utm_presets']);
    $this->assertEquals(
      ['Meta' => 'meta', 'Tiktok' => 'tiktok', 'GoogleAds' => 'google-search', 'DV360' => 'dv360', 'LinkedIn' => 'linkedin'],
      $bundle['medio_to_preset']
    );
    $this->assertEquals($this->expectedPlatPaste(), $bundle['plat_paste']);
    $this->assertEquals(
      ['instagram', 'tiktok', 'youtube', 'facebook', 'linkedin', 'whatsapp', 'newsletter', 'email', 'linktree', 'spotify', 'blog', 'influencer'],
      $bundle['ux_sources']
    );
    $this->assertEquals(
      ['influencer', 'social', 'referral', 'bio-link', 'email', 'organic', 'affiliate', 'partnership', 'cpc', 'cpm'],
      $bundle['ux_mediums']
    );
    $this->assertEquals(['meta', 'fb', 'google-pmax', 'gads', 'demandgen', 'pmax'], $bundle['reserved_src']);
  }

  /**
   * El bundle completo es serializable a JSON (simula GET /config).
   */
  public function testBundleIsJsonSerializable(): void {
    $provider = $this->container->get('utp_nomenclaturas.dictionary_provider');
    $json = json_encode($provider->getBundle(), JSON_THROW_ON_ERROR);
    $this->assertJson($json);

    $decoded = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(['adultos', 'jovenes'], $decoded['lists']['segmento']);
  }

  private function expectedLists(): array {
    return [
      'segmento' => ['adultos', 'jovenes'],
      'etapa' => ['upper', 'middle', 'lower'],
      'campus' => ['lima', 'lideres', 'def-chall', 'virtual'],
      'edad' => ['a1', 'a2', 'j1', 'j2', 'a1-a2', 'j1-j2'],
      'ubicacion' => [
        'nacional', 'lima', 'lideres', 'def-chall', 'lima-centro', 'lima-norte',
        'lima-sur', 'lima-este-ate', 'lima-este-sjl', 'arequipa', 'chiclayo',
        'iquitos', 'pucallpa', 'tacna', 'piura', 'chimbote', 'ica', 'huancayo',
        'trujillo', 'no-sedes',
      ],
      'facultad' => ['ing', 'neg', 'der', 'psi-edu', 'com', 'arq', 'sal', 'med', 'virtual'],
      'senal' => [
        'broad', 'lal', 'rmkt', 'intereses', 'int-lal', 'broad-lal',
        'int-rmkt', 'int-adv', 'lal-rmkt', 'int-lal-rmkt',
      ],
      'detalle' => [
        'gaming-tech', 'bbdd-inscritos-total', 'views-interacciones',
        'alcance-adultos', 'views-adultos', 'alcance-jovenes', 'views-jovenes',
        'alcance-virtual', 'visitas-adultos',
      ],
      'formato' => [
        'video', 'carrusel', 'ppl', 'ppv', 'collection', 'catalogo', 'sparkad',
        'rsa', 'instream', 'bumper', 'short', 'banner', 'youtube video',
      ],
      'nombre' => [
        'lo-que-el-mar-se-llevo', 'empleabilidad', 'departamenos-foco', 'modalidad',
        'medicina', 'marca', 'refuerzo-chiclayo', 'monarca', 'solavete',
        'cuando-es-tu-momento', 'podcast', 'marca-internacional', 'pasaporte',
        'tierras-de-cambio',
      ],
      'motivo' => [
        'testimonial', 'malla', 'beneficios', 'lifestyle', 'horarios',
        'convalidaciones', 'empleabilidad', 'chat_asesor', 'rotacion_carreras',
        'ugc-creator', 'casos-exito', 'porque-utp', 'campus',
      ],
      'mensaje' => [
        'estudia-trabaja', 'reinventa-carrera', 'porque-utp', 'horarios-flex',
        'convalida-termina', 'completa-solicitud', 'continua-consulta',
        'estudia-online', 'cerca-de-ti', 'jhohanna', 'dalia', 'cinthya', 'tessy',
        'carla', 'entrevista-carla', 'mundo-laboral', 'cosas-que-nadie-dijo',
        'set-grabacion', 'proximo-episodio', 'ya-esta-aqui', 'si-ni-no',
        'episodio1', 'docentes-profesionales', 'convenio-internacional',
        'influencer-finanfieras', 'influencer-diego-poblete', 'influencer-cristian-arens',
      ],
      'carrera' => [
        'no-carreras', 'ing-aero', 'ing-amb', 'ing-auto', 'ing-bio', 'ing-civ',
        'ing-elec', 'ing-elecypot', 'ing-empr', 'ing-ind', 'ing-mecat', 'ing-mec',
        'ing-minas', 'ing-segurind', 'ing-sisteinf', 'ing-soft', 'ing-telecom',
        'admin-banca', 'admin-empr', 'admin-negint', 'admin-hotel', 'admin-mkt',
        'conta', 'eco', 'med', 'enfer', 'farm-bioq', 'labclinico', 'nutri-diet',
        'obste', 'odontologia', 'tecn-med-terapia', 'psicologia', 'ciencias-com',
        'com-publi', 'dis-digitalpubli', 'dis-graf', 'arquitectura', 'dis-int',
        'derecho', 'edu-inicial', 'edu-prim', 'ingenieria', 'negocios',
      ],
      'fecha' => [
        'ene26', 'feb26', 'mar26', 'abr26', 'may26', 'jun26', 'jul26', 'ago26',
        'sep26', 'oct26', 'nov26', 'dic26',
      ],
    ];
  }

  private function expectedEtapaConditionals(): array {
    return [
      'medio' => [
        'upper' => ['Meta', 'Tiktok', 'DV360', 'LinkedIn', 'GoogleAds'],
        'middle' => ['Meta', 'Tiktok', 'DV360', 'LinkedIn', 'GoogleAds'],
        'lower' => ['Meta', 'Tiktok', 'GoogleAds', 'LinkedIn'],
      ],
      'objCamp' => [
        'upper' => ['Awareness'],
        'middle' => ['Tráfico RMKT', 'Qualifed Traffic', 'Conversiones'],
        'lower' => ['Conversiones', 'Venta'],
      ],
      'objPlat' => [
        'upper' => ['Alcance', 'Vistas', 'Vistas Completas', 'CPM'],
        'middle' => ['Clics', 'Sesiones', 'LeadWeb', 'WPP Convers Inic'],
        'lower' => ['LeadWeb', 'LeadAds', 'WPP Conversion', 'Inscritos', 'WPP Convers Inic'],
      ],
      'tipoCamp' => [
        'upper' => ['Video', 'Display', 'Media Unification', 'Demand-Gen', 'Youtube'],
        'middle' => ['Video', 'Display', 'Media Unification', 'Demand-Gen', 'Youtube', 'WPP'],
        'lower' => ['Video', 'Display', 'Search', 'PMAX', 'WPP', 'LeadAds'],
      ],
    ];
  }

  private function expectedSegmentoPilar(): array {
    return [
      'adultos' => ['calidad', 'accesibilidad', 'orgullo'],
      'jovenes' => ['calidad', 'accesibilidad', 'orgullo', 'empleabilidad'],
    ];
  }

  private function expectedFacultadNombre(): array {
    return [
      'ing' => 'Ingeniería',
      'neg' => 'Negocios',
      'der' => 'Derecho',
      'psi-edu' => 'Psicología / Educación',
      'com' => 'Comunicaciones',
      'arq' => 'Arquitectura',
      'sal' => 'Ciencias de la Salud',
      'med' => 'Medicina',
      'virtual' => 'Virtual',
    ];
  }

  private function expectedCampusFacultad(): array {
    return [
      'com' => ['lima-centro', 'lima-norte'],
      'med' => ['lima-centro', 'arequipa', 'chiclayo'],
    ];
  }

  private function expectedUbicacionGrupo(): array {
    return [
      'lima' => ['lima', 'lima-centro', 'lima-norte', 'lima-sur', 'lima-este-ate', 'lima-este-sjl'],
      'lideres' => ['lideres', 'arequipa', 'chiclayo', 'iquitos', 'pucallpa', 'tacna'],
      'def-chall' => ['def-chall', 'piura', 'chimbote', 'ica', 'huancayo', 'trujillo'],
      'virtual' => ['virtual', 'no-sedes', 'nacional'],
    ];
  }

  private function expectedSedesEspecificas(): array {
    return [
      'lima-centro', 'lima-norte', 'lima-sur', 'lima-este-ate', 'lima-este-sjl',
      'arequipa', 'chiclayo', 'iquitos', 'pucallpa', 'tacna', 'piura', 'chimbote',
      'ica', 'huancayo', 'trujillo',
    ];
  }

  private function expectedSedeGrupo(): array {
    return [
      'lima-centro' => 'Lima', 'lima-norte' => 'Lima', 'lima-sur' => 'Lima',
      'lima-este-ate' => 'Lima', 'lima-este-sjl' => 'Lima',
      'arequipa' => 'Líderes', 'chiclayo' => 'Líderes', 'iquitos' => 'Líderes',
      'pucallpa' => 'Líderes', 'tacna' => 'Líderes',
      'piura' => 'Def & Chall', 'chimbote' => 'Def & Chall', 'ica' => 'Def & Chall',
      'huancayo' => 'Def & Chall', 'trujillo' => 'Def & Chall',
    ];
  }

  private function expectedUtmPresets(): array {
    return [
      'meta' => ['plat' => 'Meta', 'source' => 'facebook', 'medium' => 'cpc', 'campaign' => '{{campaign.name}}', 'term' => '{{adset.name}}', 'content' => '{{ad.name}}', 'ga4' => 'Paid Social'],
      'tiktok' => ['plat' => 'Tiktok', 'source' => 'tiktok', 'medium' => 'cpc', 'campaign' => '__CAMPAIGN_NAME__', 'term' => '__AID_NAME__', 'content' => '__CID_NAME__', 'ga4' => 'Paid Social'],
      'google-search' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '{keyword}', 'content' => '{creative}', 'ga4' => 'Paid Search'],
      'google-pmax' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{resource group}', 'ga4' => 'Cross-network'],
      'google-demandgen' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{conjuntodeanuncio}', 'ga4' => 'Cross-network / Paid'],
      'google-video' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpv', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{creative}', 'ga4' => 'Video'],
      'google-display' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '{placement}', 'content' => '{creative}', 'ga4' => 'Display'],
      'dv360' => ['plat' => 'DV360', 'source' => 'dv360', 'medium' => 'display', 'campaign' => 'HARD', 'term' => '${CREATIVE_ID}', 'content' => '${LINE_ITEM_ID}', 'ga4' => 'Display'],
      'linkedin' => ['plat' => 'LinkedIn', 'source' => 'linkedin', 'medium' => 'cpc', 'campaign' => 'HARD', 'term' => 'HARD', 'content' => 'HARD', 'ga4' => 'Paid Social'],
    ];
  }

  private function expectedPlatPaste(): array {
    return [
      'Meta' => ['sep' => TRUE, 'where' => 'Meta › Seguimiento › Parámetros de URL (URL limpia en «URL del sitio web»)'],
      'Tiktok' => ['sep' => TRUE, 'where' => 'TikTok › Anuncio › Edit URL parameters (Auto-attach OFF)'],
      'GoogleAds' => ['sep' => TRUE, 'where' => 'Google › Sufijo de la URL final'],
      'DV360' => ['sep' => FALSE, 'where' => 'DV360 › Creative › Landing page URL (URL + parámetros juntos)'],
      'LinkedIn' => ['sep' => TRUE, 'where' => 'LinkedIn › Parámetro de URL del anuncio'],
    ];
  }

}
