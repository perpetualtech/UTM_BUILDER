<?php

namespace Drupal\Tests\utp_nomenclaturas\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utp_nomenclaturas\Service\NameBuilder;

/**
 * NameBuilder no tiene dependencias de Drupal (config/DB), así que se
 * prueba como Unit test puro — sin bootstrap de Drupal.
 *
 * @group utp_nomenclaturas
 */
class NameBuilderTest extends UnitTestCase {

  protected NameBuilder $nameBuilder;

  protected function setUp(): void {
    parent::setUp();
    $this->nameBuilder = new NameBuilder();
  }

  // ---- slug() -----------------------------------------------------

  public function testSlugTrims(): void {
    $this->assertSame('hola', $this->nameBuilder->slug('  hola  '));
  }

  public function testSlugStripsDiacritics(): void {
    $this->assertSame('Educacion-Superior', $this->nameBuilder->slug('Educación Superior'));
    $this->assertSame('nino', $this->nameBuilder->slug('niño'));
  }

  public function testSlugReplacesSpacesWithHyphens(): void {
    $this->assertSame('uno-dos-tres', $this->nameBuilder->slug('uno   dos  tres'));
  }

  public function testSlugDoesNotForceLowercase(): void {
    // §3.1 del SDD: "NO se fuerza minúsculas".
    $this->assertSame('Lima-Centro', $this->nameBuilder->slug('Lima Centro'));
  }

  public function testSlugEmptyOrNull(): void {
    $this->assertSame('', $this->nameBuilder->slug(''));
    $this->assertSame('', $this->nameBuilder->slug('   '));
    $this->assertSame('', $this->nameBuilder->slug(NULL));
  }

  // ---- campaignName() ----------------------------------------------

  public function testCampaignNameWithAllFields(): void {
    $name = $this->nameBuilder->campaignName([
      'segmento' => 'jovenes',
      'etapa' => 'upper',
      'campus' => 'lima',
      'medio' => 'Meta',
      'obj_camp' => 'Awareness',
      'obj_plat' => 'Alcance',
      'tipo_camp' => 'Video',
      'pillar_code' => 'calidad',
    ]);
    $this->assertSame('jovenes_upper_lima_Meta_Awareness_Alcance_Video_calidad', $name);
  }

  public function testCampaignNameSkipsEmptyFields(): void {
    $name = $this->nameBuilder->campaignName([
      'segmento' => 'jovenes',
      'etapa' => '',
      'campus' => 'lima',
      'medio' => NULL,
      'obj_camp' => 'Awareness',
      'obj_plat' => '',
      'tipo_camp' => 'Video',
      'pillar_code' => 'calidad',
    ]);
    $this->assertSame('jovenes_lima_Awareness_Video_calidad', $name);
  }

  public function testCampaignNameRequiresPillarCode(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->nameBuilder->campaignName([
      'segmento' => 'jovenes',
      'pillar_code' => '',
    ]);
  }

  // ---- adSetName() --------------------------------------------------

  public function testAdSetNameWithAllFields(): void {
    $name = $this->nameBuilder->adSetName([
      'edad' => 'j1-j2',
      'ubicacion' => 'lima-centro',
      'facultad' => 'ing',
      'senal' => 'broad',
      'detalle' => 'gaming-tech',
    ]);
    $this->assertSame('j1-j2_lima-centro_ing_broad_gaming-tech', $name);
  }

  public function testAdSetNameSkipsEmptyFields(): void {
    $name = $this->nameBuilder->adSetName([
      'edad' => 'j1-j2',
      'ubicacion' => '',
      'facultad' => 'ing',
      'senal' => NULL,
      'detalle' => 'Personalizado con espacios',
    ]);
    $this->assertSame('j1-j2_ing_Personalizado-con-espacios', $name);
  }

  public function testAdSetNameAllEmpty(): void {
    $this->assertSame('', $this->nameBuilder->adSetName([]));
  }

  // ---- adName() -------------------------------------------------------

  public function testAdNameWithAllFields(): void {
    $name = $this->nameBuilder->adName([
      'formato' => 'video',
      'concepto' => 'empleabilidad',
      'motivo' => 'testimonial',
      'mensaje' => 'estudia-trabaja',
      'carrera' => 'ing-soft',
      'fecha' => 'ene26',
    ]);
    $this->assertSame('video_empleabilidad_testimonial_estudia-trabaja_ing-soft_ene26', $name);
  }

  public function testAdNameSkipsEmptyFields(): void {
    $name = $this->nameBuilder->adName([
      'formato' => 'video',
      'concepto' => '',
      'motivo' => 'testimonial',
      'mensaje' => NULL,
      'carrera' => 'ing-soft',
      'fecha' => '',
    ]);
    $this->assertSame('video_testimonial_ing-soft', $name);
  }

  public function testAdNameAllEmpty(): void {
    $this->assertSame('', $this->nameBuilder->adName([]));
  }

}
