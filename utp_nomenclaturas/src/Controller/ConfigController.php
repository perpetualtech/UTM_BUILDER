<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\utp_nomenclaturas\Exception\ValidationException;
use Drupal\utp_nomenclaturas\Service\DictionaryProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Config Nivel 1/2/3 (§7.1/§11 Fase 4 del SDD): listas planas editables,
 * condicionales por etapa (D1), pilares por segmento (D2) y matriz
 * Campus×Facultad (D4). Protegido por el permiso `administer utp
 * nomenclaturas config` — separado de `edit utp nomenclaturas` (árbol),
 * igual que los distingue el propio SDD (§9.5).
 */
class ConfigController extends ControllerBase {

  public function __construct(
    protected readonly DictionaryProvider $dictionaryProvider,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('utp_nomenclaturas.dictionary_provider'));
  }

  // ==================================================================
  // Listas planas — GET/POST/DELETE /config/lists/{list_key}[/values/{code}]
  // ==================================================================

  public function getListValues(string $list_key): JsonResponse {
    $bundle = $this->dictionaryProvider->getBundle();
    if (!array_key_exists($list_key, $bundle['lists'])) {
      throw new ValidationException("'$list_key' no existe en el diccionario.", [
        ['field' => 'list_key', 'reason' => 'No existe'],
      ]);
    }
    return new JsonResponse($bundle['lists'][$list_key]);
  }

  public function addListValue(string $list_key, Request $request): JsonResponse {
    $payload = $this->decode($request);
    $value = trim((string) ($payload['value'] ?? ''));
    if ($value === '') {
      throw new ValidationException('value es requerido.', [
        ['field' => 'value', 'reason' => 'Requerido'],
      ]);
    }

    $this->dictionaryProvider->addListValue($list_key, $value);
    return new JsonResponse(['list_key' => $list_key, 'value' => $value], 201);
  }

  public function deleteListValue(string $list_key, string $code): JsonResponse {
    $removed = $this->dictionaryProvider->deleteListValue($list_key, $code);
    if (!$removed) {
      return new JsonResponse(['error' => 'No encontrado.', 'code' => 'NOT_FOUND', 'details' => []], 404);
    }
    return new JsonResponse(NULL, 204);
  }

  // ==================================================================
  // D1 — PUT /config/etapa-options/{etapa}/{field}
  // ==================================================================

  public function updateEtapaOptions(string $etapa, string $field, Request $request): JsonResponse {
    $payload = $this->decode($request);
    $values = $payload['values'] ?? NULL;
    if (!is_array($values)) {
      throw new ValidationException('values (array) es requerido.', [
        ['field' => 'values', 'reason' => 'Requerido, debe ser un array'],
      ]);
    }

    $this->dictionaryProvider->updateEtapaOptions($etapa, $field, array_map('strval', $values));
    return new JsonResponse(['etapa' => $etapa, 'field' => $field, 'values' => array_values($values)]);
  }

  // ==================================================================
  // D2 — PUT /config/segmento-pilar/{segmento}
  // ==================================================================

  public function updateSegmentoPilar(string $segmento, Request $request): JsonResponse {
    $payload = $this->decode($request);
    $pilares = $payload['pilares'] ?? NULL;
    if (!is_array($pilares)) {
      throw new ValidationException('pilares (array) es requerido.', [
        ['field' => 'pilares', 'reason' => 'Requerido, debe ser un array'],
      ]);
    }

    $this->dictionaryProvider->updateSegmentoPilar($segmento, array_map('strval', $pilares));
    return new JsonResponse(['segmento' => $segmento, 'pilares' => array_values($pilares)]);
  }

  // ==================================================================
  // D4 — PUT /config/campus-facultad
  // ==================================================================

  public function updateCampusFacultad(Request $request): JsonResponse {
    $payload = $this->decode($request);
    $matrix = $payload['matrix'] ?? NULL;
    if (!is_array($matrix)) {
      throw new ValidationException('matrix (objeto facultad => sedes[]) es requerido.', [
        ['field' => 'matrix', 'reason' => 'Requerido, debe ser un objeto'],
      ]);
    }

    $normalized = [];
    foreach ($matrix as $facultad => $sedes) {
      $normalized[(string) $facultad] = array_map('strval', is_array($sedes) ? $sedes : []);
    }

    $this->dictionaryProvider->updateCampusFacultad($normalized);
    return new JsonResponse($this->dictionaryProvider->getBundle()['campus_facultad']);
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

}
