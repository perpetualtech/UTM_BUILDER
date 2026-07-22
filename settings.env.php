<?php

/**
 * @file
 * Lectura de variables de entorno para UTP · Constructor de Nomenclaturas.
 *
 * INSTALACIÓN: añadir al FINAL de sites/default/settings.php:
 *
 *   require DRUPAL_ROOT . '/sites/default/settings.env.php';
 *
 * Carga el .env con vlucas/phpdotenv SI está presente. Si el entorno ya
 * inyecta las variables (Docker/DDEV/Lando, Apache SetEnv, systemd,
 * Kubernetes secrets...), phpdotenv es innecesario y este archivo las usa
 * directamente vía getenv(). Nunca hay credenciales en el código versionado.
 */

// Cargar .env desde la raíz del proyecto (un nivel arriba del docroot),
// solo si aún no vienen inyectadas por el entorno y phpdotenv está instalado.
$project_root = dirname(DRUPAL_ROOT);
if (getenv('DB_NAME') === FALSE
    && file_exists($project_root . '/.env')
    && class_exists(\Dotenv\Dotenv::class)) {
  \Dotenv\Dotenv::createImmutable($project_root)->safeLoad();
}

/**
 * Lee una variable de entorno con valor por defecto.
 */
$env = static function (string $key, $default = NULL) {
  $value = getenv($key);
  return $value === FALSE ? $default : $value;
};

// -- Base de datos ----------------------------------------------------------
$databases['default']['default'] = [
  'driver'    => $env('DB_DRIVER', 'mysql'),
  'host'      => $env('DB_HOST', 'localhost'),
  'port'      => $env('DB_PORT', '3306'),
  'database'  => $env('DB_NAME'),
  'username'  => $env('DB_USER'),
  'password'  => $env('DB_PASSWORD'),
  'prefix'    => $env('DB_PREFIX', ''),
  'collation' => 'utf8mb4_general_ci',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// -- Seguridad ----------------------------------------------------------------
$settings['hash_salt'] = $env('DRUPAL_HASH_SALT', '');

$patterns = array_filter(array_map(
  'trim',
  explode(',', (string) $env('TRUSTED_HOST_PATTERNS', ''))
));
if (!empty($patterns)) {
  $settings['trusted_host_patterns'] = $patterns;
}

// -- Configuración ------------------------------------------------------------
$settings['config_sync_directory'] = $env('CONFIG_SYNC_DIRECTORY', '../config/sync');

// -- Ajustes por entorno --------------------------------------------------------
switch ($env('APP_ENV', 'prod')) {
  case 'dev':
    $config['system.logging']['error_level'] = 'verbose';
    $settings['cache']['bins']['render'] = 'cache.backend.null';
    $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
    $settings['skip_permissions_hardening'] = TRUE;
    break;

  case 'staging':
  case 'prod':
  default:
    $config['system.logging']['error_level'] = 'hide';
    break;
}
