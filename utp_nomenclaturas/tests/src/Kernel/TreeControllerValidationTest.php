<?php

namespace Drupal\Tests\utp_nomenclaturas\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Acceptance criteria de la Fase 1 (§11 del SDD): 409 en nombre duplicado,
 * 422 en condicional violado. Sin servidor HTTP real disponible, se
 * despacha un Request de verdad a través de `http_kernel` dentro del
 * Kernel test — patrón válido, sin necesitar BrowserTestBase.
 *
 * @group utp_nomenclaturas
 */
class TreeControllerValidationTest extends KernelTestBase {

  use UserCreationTrait;

  protected static $modules = ['system', 'user', 'utp_nomenclaturas'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('campaign');
    $this->installEntitySchema('ad_set');
    $this->installEntitySchema('ad');
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

  /**
   * Payload válido de referencia: pilar 'calidad' es válido para ambos
   * segmentos; etapa 'upper' permite Meta/Awareness/Alcance/Video (Anexo A).
   */
  private function validCampaignPayload(): array {
    return [
      'pillar_code' => 'calidad',
      'meta' => [
        'segmento' => 'jovenes',
        'etapa' => 'upper',
        'campus' => 'lima',
        'medio' => 'Meta',
        'obj_camp' => 'Awareness',
        'obj_plat' => 'Alcance',
        'tipo_camp' => 'Video',
      ],
    ];
  }

  public function testCreateCampaignValidReturns201(): void {
    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $this->validCampaignPayload()));
    $this->assertEquals(201, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('jovenes_upper_lima_Meta_Awareness_Alcance_Video_calidad', $data['name']);
  }

  public function testCreateCampaignDuplicateReturns409(): void {
    $payload = $this->validCampaignPayload();

    $first = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $payload));
    $this->assertEquals(201, $first->getStatusCode());

    $second = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $payload));
    $this->assertEquals(409, $second->getStatusCode());

    $data = json_decode($second->getContent(), TRUE);
    $this->assertSame('DUPLICATE_NAME', $data['code']);
    $this->assertSame('campaign', $data['details']['entity_type']);
  }

  public function testCreateCampaignInvalidD1ConditionalReturns422(): void {
    $payload = $this->validCampaignPayload();
    // 'DV360' no está permitido en etapa 'lower' (Anexo A: lower.medio no lo incluye).
    $payload['meta']['etapa'] = 'lower';
    $payload['meta']['medio'] = 'DV360';

    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $payload));
    $this->assertEquals(422, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('VALIDATION_FAILED', $data['code']);
    $this->assertSame('D1', $data['details']['conditional_id']);
  }

  public function testCreateCampaignInvalidD2ConditionalReturns422(): void {
    $payload = $this->validCampaignPayload();
    // 'empleabilidad' es exclusivo de segmento 'jovenes' (§3.2 D2).
    $payload['pillar_code'] = 'empleabilidad';
    $payload['meta']['segmento'] = 'adultos';

    $response = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $payload));
    $this->assertEquals(422, $response->getStatusCode());

    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('VALIDATION_FAILED', $data['code']);
    $this->assertSame('D2', $data['details']['conditional_id']);
  }

  public function testCreateAdSetInvalidD3ConditionalReturns422(): void {
    $campaignResponse = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $this->validCampaignPayload()));
    $campaignUuid = json_decode($campaignResponse->getContent(), TRUE)['uuid'];

    // 'med' está restringida a [lima-centro, arequipa, chiclayo]; 'lima-norte' no califica.
    $response = $this->dispatch($this->jsonRequest(
      'POST',
      "/api/utp-nomenclaturas/v1/campaigns/$campaignUuid/ad-sets",
      ['meta' => ['edad' => 'j1', 'ubicacion' => 'lima-norte', 'facultad' => 'med', 'senal' => 'broad', 'detalle' => '']]
    ));

    $this->assertEquals(422, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame('D3', $data['details']['conditional_id']);
  }

  /**
   * Prueba directa del FK real (ADR §6.2, AdSet/Ad::ON_DELETE_CASCADE):
   * borrar la campaña debe borrar en cascada su conjunto y su anuncio,
   * sin que TreeManager tenga que borrarlos a mano — es la Entity API de
   * Drupal la que lo hace al resolver la referencia con cascada.
   */
  public function testDeleteCampaignCascadesToAdSetAndAd(): void {
    $campaignResponse = $this->dispatch($this->jsonRequest('POST', '/api/utp-nomenclaturas/v1/campaigns', $this->validCampaignPayload()));
    $campaignUuid = json_decode($campaignResponse->getContent(), TRUE)['uuid'];

    $adSetResponse = $this->dispatch($this->jsonRequest(
      'POST',
      "/api/utp-nomenclaturas/v1/campaigns/$campaignUuid/ad-sets",
      ['meta' => ['edad' => 'j1', 'ubicacion' => 'lima', 'facultad' => 'ing', 'senal' => 'broad', 'detalle' => '']]
    ));
    $this->assertEquals(201, $adSetResponse->getStatusCode());
    $adSetUuid = json_decode($adSetResponse->getContent(), TRUE)['uuid'];

    $adResponse = $this->dispatch($this->jsonRequest(
      'POST',
      "/api/utp-nomenclaturas/v1/ad-sets/$adSetUuid/ads",
      ['meta' => ['formato' => 'video', 'nombre' => 'marca', 'motivo' => 'testimonial', 'mensaje' => '', 'carrera' => 'no-carreras', 'fecha' => 'ene26'], 'url' => 'https://utp.edu.pe']
    ));
    $this->assertEquals(201, $adResponse->getStatusCode());
    $adUuid = json_decode($adResponse->getContent(), TRUE)['uuid'];

    // Confirmar que existen antes de borrar (si no, el resto del test no prueba nada).
    $adSetStorage = $this->container->get('entity_type.manager')->getStorage('ad_set');
    $adStorage = $this->container->get('entity_type.manager')->getStorage('ad');
    $this->assertNotEmpty($adSetStorage->loadByProperties(['uuid' => $adSetUuid]));
    $this->assertNotEmpty($adStorage->loadByProperties(['uuid' => $adUuid]));

    $deleteResponse = $this->dispatch($this->jsonRequest('DELETE', "/api/utp-nomenclaturas/v1/campaigns/$campaignUuid"));
    $this->assertEquals(204, $deleteResponse->getStatusCode());

    // La cascada real de la Entity API (ON_DELETE_CASCADE) debe haber
    // borrado el conjunto y el anuncio sin que nadie los borre a mano.
    $adSetStorage->resetCache();
    $adStorage->resetCache();
    $this->assertEmpty($adSetStorage->loadByProperties(['uuid' => $adSetUuid]), 'El conjunto debía borrarse en cascada junto con la campaña.');
    $this->assertEmpty($adStorage->loadByProperties(['uuid' => $adUuid]), 'El anuncio debía borrarse en cascada junto con la campaña.');
  }

}
