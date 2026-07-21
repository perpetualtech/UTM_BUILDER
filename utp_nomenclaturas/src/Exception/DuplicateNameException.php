<?php

namespace Drupal\utp_nomenclaturas\Exception;

/**
 * 409 — nombre derivado no único dentro de su scope (§3.3 del SDD).
 */
class DuplicateNameException extends UtpNomenclaturaException {

  public function __construct(
    protected readonly string $entityType,
    protected readonly string $name,
    protected readonly string $uniquenessScope,
  ) {
    parent::__construct(
      409,
      'DUPLICATE_NAME',
      sprintf("%s '%s' ya existe dentro de %s.", $entityType, $name, $uniquenessScope)
    );
  }

  public function getDetails(): array {
    return [
      'entity_type' => $this->entityType,
      'name' => $this->name,
      'uniqueness_scope' => $this->uniquenessScope,
    ];
  }

}
