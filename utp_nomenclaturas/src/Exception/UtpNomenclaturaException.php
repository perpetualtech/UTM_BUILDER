<?php

namespace Drupal\utp_nomenclaturas\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base de las excepciones de dominio del módulo.
 *
 * Extiende HttpException para que el status code se propague de forma
 * idiomática; `UtpNomenclaturaExceptionSubscriber` la serializa como
 * `{error, code, details}` (§7 del SDD) para las rutas bajo
 * `/api/utp-nomenclaturas/*`.
 */
abstract class UtpNomenclaturaException extends HttpException {

  public function __construct(
    protected readonly int $errorStatusCode,
    protected readonly string $errorCode,
    string $message,
  ) {
    parent::__construct($this->errorStatusCode, $message);
  }

  public function getErrorCode(): string {
    return $this->errorCode;
  }

  /**
   * Detalles adicionales para la respuesta JSON. Las subclases lo sobreescriben.
   */
  public function getDetails(): array {
    return [];
  }

}
