<?php

namespace Drupal\utp_nomenclaturas\EventSubscriber;

use Drupal\utp_nomenclaturas\Exception\UtpNomenclaturaException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Traduce UtpNomenclaturaException a `{error, code, details}` (§7 del SDD).
 *
 * Solo actúa bajo /api/utp-nomenclaturas/* para no interferir con el
 * manejo de excepciones del resto del sitio Drupal.
 */
class UtpNomenclaturaExceptionSubscriber implements EventSubscriberInterface {

  private const API_PATH_PREFIX = '/api/utp-nomenclaturas/';

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if (!$exception instanceof UtpNomenclaturaException) {
      return;
    }

    if (!str_starts_with($event->getRequest()->getPathInfo(), self::API_PATH_PREFIX)) {
      return;
    }

    $event->setResponse(new JsonResponse([
      'error' => $exception->getMessage(),
      'code' => $exception->getErrorCode(),
      'details' => $exception->getDetails(),
    ], $exception->getStatusCode()));
  }

}
