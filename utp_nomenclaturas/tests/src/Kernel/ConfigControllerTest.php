<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Acceptance criteria de la Fase 4 (§11 del SDD): "editar la matriz
 * impacta D3 en vivo". Cubre también D1 (etapa-options) y D2
 * (segmento-pilar) en vivo, el permiso `administer utp nomenclaturas
 * config` (separado de `edit`) y CSRF (`_csrf_request_header_token`) —
 * mismo patrón de dispatch real que TreeControllerValidationTest (Fase 1).
 *
 * @group utp_nomenclaturas
 */
class ConfigControllerTest extends KernelTestBase {

  use UserCreationTrait;

  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['utp_nomenclaturas']);
    $this->container->get('router.builder')->rebuild();

    $this->setUpCurrentUser([], [
      'access utp nomenclaturas',
      'edit utp nomenclaturas',
      'administer utp nomenclaturas config',
    ]);
  }

  private function dispatch(Request $request) {
    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::MAIN_REQUEST);
  }

  private function jsonRequest(string $method, string $path, array $body = [], bool $withCsrf = TRUE): Request {
    $headers = ['CONTENT_TYPE' => 'application/json'];
    if ($withCsrf) {
      $headers['HTTP_X_CSRF_TOKEN'] = $this->container->get('csrf_token')->get();
    }
    return Request::create($path, $method, [], [], [], $headers, $body ? json_encode($body) : NULL);
  }

  private function bundle(): array {
    return $this->container->get('utp_nomenclaturas.dictionary_provider')->getBundle();
  }

  // ==================================================================
  // Listas planas
  // ==================================================================

  public function testAddListValueReturns201AndAppearsInBundle(): void {
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/campus/values', ['value' => 'nueva-sede']));
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertContains('nueva-sede', $this->bundle()['lists']['campus']);
  }

  public function testAddListValueDuplicateReturns409(): void {
    $first = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/campus/values', ['value' => 'nueva-sede']));
    $this->assertEquals(201, $first->getStatusCode());

    $second = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/campus/values', ['value' => 'nueva-sede']));
    $this->assertEquals(409, $second->getStatusCode());
    $this->assertSame('DUPLICATE_NAME', json_decode($second->getContent(), TRUE)['code']);
  }

  public function testAddValueToNonEditableListReturns422(): void {
    // 'etapa' existe en bundle.lists pero no es editable por este endpoint
    // genérico (es fija, §3.2).
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/etapa/values', ['value' => 'x']));
    $this->assertEquals(422, $response->getStatusCode());
  }

  public function testDeleteListValueReturns204AndDisappearsFromBundle(): void {
    $response = $this->dispatch($this->jsonRequest('DELETE', '/api/utp-nomenclaturas/v1/config/lists/campus/values/lima'));
    $this->assertEquals(204, $response->getStatusCode());
    $this->assertNotContains('lima', $this->bundle()['lists']['campus']);
  }

  public function testDeleteNonExistentListValueReturns404(): void {
    $response = $this->dispatch($this->jsonRequest('DELETE', '/api/utp-nomenclaturas/v1/config/lists/campus/values/no-existe'));
    $this->assertEquals(404, $response->getStatusCode());
  }

  // ==================================================================
  // D1 — etapa-options en vivo
  // ==================================================================

  public function testUpdateEtapaOptionsReflectsLiveInBundle(): void {
    $this->assertNotContains('Snapchat', $this->bundle()['etapa_conditionals']['medio']['upper']);

    $response = $this->dispatch($this->jsonRequest(
      'PUT',
      '/api/utp-nomenclaturas/v1/config/etapa-options/upper/medio',
      ['values' => ['Meta', 'Snapchat']]
    ));
    $this->assertEquals(200, $response->getStatusCode());

    $this->assertSame(['Meta', 'Snapchat'], $this->bundle()['etapa_conditionals']['medio']['upper']);
  }

  // ==================================================================
  // D2 — segmento-pilar en vivo
  // ==================================================================

  public function testUpdateSegmentoPilarReflectsLiveInBundle(): void {
    $response = $this->dispatch($this->jsonRequest(
      'PUT',
      '/api/utp-nomenclaturas/v1/config/segmento-pilar/adultos',
      ['pilares' => ['calidad']]
    ));
    $this->assertEquals(200, $response->getStatusCode());

    $bundle = $this->bundle();
    $this->assertSame(['calidad'], $bundle['segmento_pilar']['adultos']);

    // D2 usa el bundle en vivo — "accesibilidad" ya no es válido para adultos.
    $provider = $this->container->get('utp_nomenclaturas.dictionary_provider');
    $this->assertFalse($provider->isValidPilarSegmentoCombination('accesibilidad', 'adultos'));
  }

  // ==================================================================
  // D4 — matriz Campus×Facultad en vivo (AC principal de la Fase 4)
  // ==================================================================

  public function testUpdateCampusFacultadImpactsD3Live(): void {
    $provider = $this->container->get('utp_nomenclaturas.dictionary_provider');

    // Antes: 'ing' no tiene restricción → disponible en cualquier ubicación.
    $this->assertTrue($provider->isFacultadValidForUbicacion('ing', 'arequipa'));

    $response = $this->dispatch($this->jsonRequest(
      'PUT',
      '/api/utp-nomenclaturas/v1/config/campus-facultad',
      ['matrix' => ['ing' => ['lima-centro', 'lima-norte']]]
    ));
    $this->assertEquals(200, $response->getStatusCode());

    // Después: restringida a Lima → ya NO disponible en arequipa (D3 en vivo).
    $this->assertFalse($provider->isFacultadValidForUbicacion('ing', 'arequipa'));
    $this->assertTrue($provider->isFacultadValidForUbicacion('ing', 'lima-centro'));
  }

  public function testCampusFacultadCoveringAllSedesRemovesRestriction(): void {
    $bundle = $this->bundle();
    $todasLasSedes = $bundle['sedes_especificas'];

    // 'com' viene restringida desde el seed (Anexo A). Si se la ofrece en
    // TODAS las sedes específicas, la restricción debe desaparecer.
    $response = $this->dispatch($this->jsonRequest(
      'PUT',
      '/api/utp-nomenclaturas/v1/config/campus-facultad',
      ['matrix' => ['com' => $todasLasSedes]]
    ));
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertArrayNotHasKey('com', $this->bundle()['campus_facultad']);

    $provider = $this->container->get('utp_nomenclaturas.dictionary_provider');
    $this->assertTrue($provider->isFacultadValidForUbicacion('com', 'arequipa'));
  }

  // ==================================================================
  // Permisos y CSRF
  // ==================================================================

  public function testConfigMutationWithoutAdministerPermissionReturns403(): void {
    $this->setUpCurrentUser([], ['access utp nomenclaturas', 'edit utp nomenclaturas']);
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/campus/values', ['value' => 'x']));
    $this->assertEquals(403, $response->getStatusCode());
  }

  public function testConfigMutationWithoutCsrfTokenReturns403(): void {
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/config/lists/campus/values', ['value' => 'x'], withCsrf: FALSE));
    $this->assertEquals(403, $response->getStatusCode());
  }

}
