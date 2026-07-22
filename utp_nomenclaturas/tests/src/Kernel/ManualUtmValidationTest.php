<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * §3.4 del SDD: "UTM manual: ... source+medium obligatorios", y la misma
 * regla se aplica también a url/qs (§3.3, campos requeridos en la entidad
 * ManualUtm). Mismo patrón de dispatch real que TreeControllerValidationTest
 * (Fase 1).
 *
 * @group utp_nomenclaturas
 */
class ManualUtmValidationTest extends KernelTestBase {

  use UserCreationTrait;

  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('manual_utm');
    $this->installConfig(['utp_nomenclaturas']);
    $this->container->get('router.builder')->rebuild();

    $this->setUpCurrentUser([], ['access utp nomenclaturas', 'edit utp nomenclaturas']);
  }

  private function dispatch(Request $request) {
    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::MAIN_REQUEST);
  }

  /**
   * Adjunta X-CSRF-Token (Fase 4, §9.5) — las rutas de escritura exigen
   * `_csrf_request_header_token: 'TRUE'` desde Fase 4.
   */
  private function jsonRequest(string $method, string $path, array $body = []): Request {
    return Request::create(
      $path,
      $method,
      [],
      [],
      [],
      [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CSRF_TOKEN' => $this->container->get('csrf_token')->get(),
      ],
      $body ? json_encode($body) : NULL,
    );
  }

  private function validManualPayload(): array {
    return [
      'utm_source' => 'instagram',
      'utm_medium' => 'influencer',
      'utm_campaign' => 'lo-que-el-mar-se-llevo',
      'url' => 'https://instagram.com/utp.oficial',
      'qs' => 'utm_source=instagram&utm_medium=influencer&utm_campaign=lo-que-el-mar-se-llevo',
    ];
  }

  public function testCreateManualValidReturns201(): void {
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/utms/manual', $this->validManualPayload()));
    $this->assertEquals(201, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('instagram', $data['utm_source']);
    $this->assertSame('influencer', $data['utm_medium']);
    $this->assertNotEmpty($data['uuid']);
  }

  /**
   * @dataProvider requiredFieldProvider
   */
  public function testCreateManualMissingRequiredFieldReturns422(string $missingField): void {
    $payload = $this->validManualPayload();
    unset($payload[$missingField]);

    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/utms/manual', $payload));
    $this->assertEquals(422, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('VALIDATION_FAILED', $data['code']);
    $fields = array_column($data['details']['violations'], 'field');
    $this->assertContains($missingField, $fields);
  }

  public function requiredFieldProvider(): array {
    return [
      'utm_source' => ['utm_source'],
      'utm_medium' => ['utm_medium'],
      'url' => ['url'],
      'qs' => ['qs'],
    ];
  }

  public function testListManualReturnsMostRecentFirst(): void {
    $first = $this->validManualPayload();
    $second = ['utm_source' => 'youtube', 'utm_medium' => 'social'] + $this->validManualPayload();

    $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/utms/manual', $first));
    $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/utms/manual', $second));

    $response = $this->dispatch(Request::create('/api/utp-nomenclaturas/v1/utms/manual', 'GET'));
    $this->assertEquals(200, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertCount(2, $data);
    $this->assertSame('youtube', $data[0]['utm_source']);
  }

  public function testDeleteManualReturns204(): void {
    $created = json_decode(
      $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/utms/manual', $this->validManualPayload()))->getContent(),
      TRUE
    );

    $response = $this->dispatch($this->jsonRequest('DELETE', '/api/utp-nomenclaturas/v1/utms/manual/' . $created['uuid']));
    $this->assertEquals(204, $response->getStatusCode());

    $list = json_decode(
      $this->dispatch(Request::create('/api/utp-nomenclaturas/v1/utms/manual', 'GET'))->getContent(),
      TRUE
    );
    $this->assertCount(0, $list);
  }

}
