<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\utp_nomenclaturas\Service\DictionaryProvider;

/**
 * D1-D3 (§3.2 del SDD) resueltos por DictionaryProvider, contra la config
 * real sembrada en Fase 0 (Anexo A) — sin datos de ejemplo inventados.
 *
 * @group utp_nomenclaturas
 */
class DictionaryConditionalTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  protected DictionaryProvider $provider;

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['utp_nomenclaturas']);
    $this->provider = $this->container->get('utp_nomenclaturas.dictionary_provider');
  }

  // ---- D1: etapa → medio/objCamp/objPlat/tipoCamp --------------------

  public function testD1UpperMedioIncludesAllFivePlatforms(): void {
    $this->assertEquals(
      ['Meta', 'Tiktok', 'DV360', 'LinkedIn', 'GoogleAds'],
      $this->provider->getOptionsForEtapa('upper', 'medio')
    );
  }

  public function testD1LowerMedioExcludesDV360(): void {
    $options = $this->provider->getOptionsForEtapa('lower', 'medio');
    $this->assertSame(['Meta', 'Tiktok', 'GoogleAds', 'LinkedIn'], $options);
    $this->assertNotContains('DV360', $options);
  }

  public function testD1MiddleObjCamp(): void {
    $this->assertSame(
      ['Tráfico RMKT', 'Qualifed Traffic', 'Conversiones'],
      $this->provider->getOptionsForEtapa('middle', 'objCamp')
    );
  }

  public function testD1UnknownEtapaReturnsEmpty(): void {
    $this->assertSame([], $this->provider->getOptionsForEtapa('etapa-inexistente', 'medio'));
  }

  public function testD1InvalidFieldThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->provider->getOptionsForEtapa('upper', 'segmento');
  }

  // ---- D2: segmento → pilar -------------------------------------------

  public function testD2JovenesIncludesEmpleabilidad(): void {
    $this->assertContains('empleabilidad', $this->provider->getPilaresForSegmento('jovenes'));
  }

  public function testD2AdultosExcludesEmpleabilidad(): void {
    $this->assertNotContains('empleabilidad', $this->provider->getPilaresForSegmento('adultos'));
    $this->assertSame(['calidad', 'accesibilidad', 'orgullo'], $this->provider->getPilaresForSegmento('adultos'));
  }

  public function testD2EmpleabilidadOnlyValidForJovenes(): void {
    $this->assertFalse($this->provider->isValidPilarSegmentoCombination('empleabilidad', 'adultos'));
    $this->assertTrue($this->provider->isValidPilarSegmentoCombination('empleabilidad', 'jovenes'));
  }

  public function testD2CommonPilarValidForBothSegmentos(): void {
    $this->assertTrue($this->provider->isValidPilarSegmentoCombination('calidad', 'adultos'));
    $this->assertTrue($this->provider->isValidPilarSegmentoCombination('calidad', 'jovenes'));
  }

  // ---- D3: ubicacion → facultad ---------------------------------------

  public function testD3UnrestrictedFacultadAlwaysAvailable(): void {
    // 'ing' no aparece en campus_facultad → sin restricción.
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('ing', 'trujillo'));
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('ing', 'lima-centro'));
  }

  public function testD3RestrictedFacultadValidAtExactSede(): void {
    // 'med' restringida a [lima-centro, arequipa, chiclayo].
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('med', 'lima-centro'));
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('med', 'arequipa'));
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('med', 'chiclayo'));
  }

  public function testD3RestrictedFacultadInvalidOutsideSedes(): void {
    // 'med' no está disponible en 'lima-norte' (no está en su lista, y
    // 'lima-norte' no es un grupo).
    $this->assertFalse($this->provider->isFacultadValidForUbicacion('med', 'lima-norte'));
  }

  public function testD3RestrictedFacultadValidViaGroupIntersection(): void {
    // Grupo 'lideres' incluye arequipa/chiclayo, que sí están en la lista de 'med'.
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('med', 'lideres'));
    // Grupo 'lima' incluye lima-centro/lima-norte, que están en la lista de 'com'.
    $this->assertTrue($this->provider->isFacultadValidForUbicacion('com', 'lima'));
  }

  public function testD3RestrictedFacultadInvalidViaGroupWithoutIntersection(): void {
    // Grupo 'def-chall' (piura, chimbote, ica, huancayo, trujillo) no
    // intersecta ni la lista de 'com' ni la de 'med'.
    $this->assertFalse($this->provider->isFacultadValidForUbicacion('com', 'def-chall'));
    $this->assertFalse($this->provider->isFacultadValidForUbicacion('med', 'def-chall'));
  }

  public function testD3GetFacultadesForUbicacionExcludesOutOfScopeRestricted(): void {
    $facultades = $this->provider->getFacultadesForUbicacion('def-chall');
    $this->assertContains('ing', $facultades);
    $this->assertNotContains('com', $facultades);
    $this->assertNotContains('med', $facultades);
  }

  // ---- codeExistsInList() ----------------------------------------------

  public function testCodeExistsInListValidAndInvalidValue(): void {
    $this->assertTrue($this->provider->codeExistsInList('segmento', 'jovenes'));
    $this->assertFalse($this->provider->codeExistsInList('segmento', 'codigo-inexistente'));
  }

  public function testCodeExistsInListInvalidListKeyThrows(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->provider->codeExistsInList('lista-inexistente', 'cualquier-valor');
  }

}
