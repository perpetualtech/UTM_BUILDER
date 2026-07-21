<?php

namespace Drupal\utp_nomenclaturas\Exception;

/**
 * 422 — condicional D1/D2/D3 violado (§3.2 del SDD).
 */
class ConditionalViolationException extends ValidationException {

  /**
   * @param string $conditionalId D1|D2|D3.
   * @param array<int, array{field: string, reason: string}> $violations
   */
  public function __construct(
    protected readonly string $conditionalId,
    string $message,
    array $violations = [],
  ) {
    parent::__construct($message, $violations);
  }

  public function getDetails(): array {
    return parent::getDetails() + ['conditional_id' => $this->conditionalId];
  }

}
