<?php

namespace Drupal\utp_nomenclaturas\Exception;

/**
 * 422 — código inexistente en el diccionario activo, o campo requerido
 * faltante. Para condicionales D1-D3 específicamente usar
 * ConditionalViolationException (subclase).
 */
class ValidationException extends UtpNomenclaturaException {

  /**
   * @param array<int, array{field: string, reason: string}> $violations
   */
  public function __construct(
    string $message,
    protected readonly array $violations = [],
  ) {
    parent::__construct(422, 'VALIDATION_FAILED', $message);
  }

  public function getDetails(): array {
    return ['violations' => $this->violations];
  }

}
