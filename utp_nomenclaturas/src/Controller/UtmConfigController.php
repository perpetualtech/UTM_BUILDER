<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\utp_nomenclaturas\Service\DictionaryProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sirve el bundle de configuración (§7.1 del SDD: GET /config).
 *
 * Ruta mínima de solo lectura para Fase 0 — sin CSRF/permisos finos
 * todavía (eso es Fase 4, §9.5 del SDD).
 */
class UtmConfigController extends ControllerBase {

  public function __construct(
    protected readonly DictionaryProvider $dictionaryProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('utp_nomenclaturas.dictionary_provider'));
  }

  /**
   * GET /api/utp-nomenclaturas/v1/config.
   */
  public function getConfig(): CacheableJsonResponse {
    $response = new CacheableJsonResponse($this->dictionaryProvider->getBundle());

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags([
      'config:utp_nomenclaturas.dictionary',
      'config:utp_nomenclaturas.utm_presets',
    ]);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

}
