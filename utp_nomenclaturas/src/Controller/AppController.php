<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve `frontend/index.html`: página HTML/CSS/JS autocontenida, sin
 * framework ni build, análoga a `UTP-Nomenclaturas.html` pero con
 * `fetch()` contra `/api/utp-nomenclaturas/v1/*` en vez de `localStorage`.
 *
 * Se devuelve como Response cruda, no como render array: el documento
 * trae su propio `<html><head><style>`, y montarlo dentro del theme de
 * administración duplicaría la estructura. Lo único inyectado por Drupal
 * es `window.UTP_SETTINGS` (URL base de la API + token CSRF) — ver la
 * capa de comunicación al inicio del `<script>` del archivo servido.
 */
class AppController extends ControllerBase {

  private const API_BASE = '/api/utp-nomenclaturas/v1';

  public function __construct(
    protected readonly ModuleExtensionList $moduleExtensionList,
    protected readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.module'),
      $container->get('csrf_token'),
    );
  }

  /**
   * GET /admin/utp/nomenclaturas.
   */
  public function app(): Response {
    $modulePath = \DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('utp_nomenclaturas');
    $appPath = $modulePath . '/frontend/index.html';

    if (!is_file($appPath)) {
      return new Response(
        '<p style="font-family:sans-serif;padding:40px">No se encontró frontend/index.html del módulo utp_nomenclaturas.</p>',
        500
      );
    }

    $settings = json_encode([
      'apiBase' => self::API_BASE,
      // Sin argumento (seed vacío) — es el mismo token que valida el
      // access checker core `_csrf_request_header_token`
      // (`CsrfRequestHeaderAccessCheck`), el que también genera la ruta
      // core `/session/token`.
      'csrfToken' => $this->csrfToken->get(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    $html = file_get_contents($appPath);
    $html = str_replace(
      '<body>',
      '<body>' . "\n" . '<script>window.UTP_SETTINGS=' . $settings . ';</script>',
      $html,
      $count
    );
    if ($count === 0) {
      // No se encontró <body> — servir igual, sin settings inyectadas
      // (la app cae a la ruta relativa por defecto de la API, ver
      // apiFetch() en el archivo).
      $html = file_get_contents($appPath);
    }

    return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
  }

}
