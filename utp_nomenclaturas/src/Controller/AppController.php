<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve el builder como página HTML/CSS/JS plana (sin framework, sin paso
 * de build) — `js/app/index.html`, una sola página autocontenida como
 * `UTP-Nomenclaturas.html` original, pero con `fetch()` real contra
 * `/api/utp-nomenclaturas/v1/*` en vez de `localStorage`.
 *
 * Se devuelve como Response cruda (no un render array de Drupal): la
 * página ya trae su propio `<html><head><style>`, así que montarla dentro
 * del theme de administración de Drupal duplicaría el documento. El único
 * dato que Drupal necesita inyectarle es la URL base de la API y el token
 * CSRF, vía `window.UTP_SETTINGS` (ver la capa "CAPA DE COMUNICACIÓN CON
 * EL BACKEND" al inicio del `<script>` del archivo).
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
    $appPath = $modulePath . '/js/app/index.html';

    if (!is_file($appPath)) {
      return new Response(
        '<p style="font-family:sans-serif;padding:40px">No se encontró js/app/index.html del módulo utp_nomenclaturas.</p>',
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
