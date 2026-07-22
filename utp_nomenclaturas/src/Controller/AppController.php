<?php

namespace Drupal\utp_nomenclaturas\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Monta la SPA (§9.5 del SDD): `<div id="utp-nomen-root">` + el build de
 * Vite (`utp-nomenclaturas-app/`) copiado a `js/dist/`.
 *
 * Vite hashea los nombres de archivo de salida, así que no se puede
 * declarar un `library` estático en `utp_nomenclaturas.libraries.yml` con
 * un `js:`/`css:` fijo. En su lugar se lee `js/dist/.vite/manifest.json`
 * en runtime (mismo patrón que usan Laravel/Rails/Django para montar SPAs
 * Vite) y se adjuntan las etiquetas reales vía `#attached.html_head`.
 *
 * Paso manual (sin CI en esta sesión): `cd utp-nomenclaturas-app && npm
 * run build`, luego copiar `dist/` a `utp_nomenclaturas/js/dist/`.
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
  public function app(): array {
    $modulePath = $this->moduleExtensionList->getPath('utp_nomenclaturas');
    $manifestPath = \DRUPAL_ROOT . '/' . $modulePath . '/js/dist/.vite/manifest.json';

    $build = [
      '#markup' => '<div id="utp-nomen-root"></div>',
      '#attached' => [
        'drupalSettings' => [
          'utpNomenclaturas' => [
            'apiBase' => self::API_BASE,
            // Sin argumento (seed vacío) — es el mismo token que valida
            // el access checker core `_csrf_request_header_token`
            // (`CsrfRequestHeaderAccessCheck`), el que también genera la
            // ruta core `/session/token`.
            'csrfToken' => $this->csrfToken->get(),
          ],
        ],
      ],
    ];

    if (!is_file($manifestPath)) {
      $build['#markup'] .= '<p><em>Build de Vite no encontrado — corré "npm run build" en utp-nomenclaturas-app/ y copiá dist/ a js/dist/.</em></p>';
      return $build;
    }

    $manifest = json_decode(file_get_contents($manifestPath), TRUE) ?? [];
    $entry = NULL;
    foreach ($manifest as $chunk) {
      if (!empty($chunk['isEntry'])) {
        $entry = $chunk;
        break;
      }
    }

    if ($entry === NULL) {
      $build['#markup'] .= '<p><em>manifest.json sin entry point — build de Vite inválido.</em></p>';
      return $build;
    }

    $assetsBase = \base_path() . $modulePath . '/js/dist/';
    $tags = [];
    foreach ($entry['css'] ?? [] as $css) {
      $tags[] = ['#tag' => 'link', '#attributes' => ['rel' => 'stylesheet', 'href' => $assetsBase . $css]];
    }
    $tags[] = [
      '#tag' => 'script',
      '#attributes' => ['type' => 'module', 'src' => $assetsBase . $entry['file']],
    ];

    foreach ($tags as $i => $tag) {
      $build['#attached']['html_head'][] = [$tag, 'utp_nomenclaturas_app_asset_' . $i];
    }

    return $build;
  }

}
