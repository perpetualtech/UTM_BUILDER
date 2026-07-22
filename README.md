# UTP · Constructor de Nomenclaturas

Herramienta para estandarizar la nomenclatura de campañas, conjuntos de
anuncios y anuncios de UTP, y derivar automáticamente sus parámetros UTM.
Reemplaza una herramienta de referencia en un solo archivo HTML (que se
mantiene en el repo como fuente de verdad funcional/de migración) por un
módulo Drupal con API REST propia y una SPA en React.

## Arquitectura

| Carpeta | Qué es |
|---|---|
| [`utp_nomenclaturas/`](utp_nomenclaturas) | Módulo Drupal 10.3+/11: entidades de contenido (Campaña, Conjunto, Anuncio, UTM manual), diccionario y reglas de negocio como Configuration API, API REST bajo `/api/utp-nomenclaturas/v1/*`, ruta admin que monta la SPA. |
| [`utp-nomenclaturas-app/`](utp-nomenclaturas-app) | SPA en React 19 + TypeScript (Vite, Tailwind v4, shadcn/ui, TanStack Query, Zustand). En producción la sirve el propio módulo Drupal; en desarrollo corre standalone contra mocks (MSW). |
| [`UTP-Nomenclaturas.html`](UTP-Nomenclaturas.html) | Herramienta de referencia original (un solo HTML, sin backend). Fuente de verdad de las reglas de negocio y punto de partida para migrar datos reales. |
| [`SDD-UTP-Nomenclaturas.md`](SDD-UTP-Nomenclaturas.md) | Especificación completa de arquitectura y reglas de negocio (dominio, contrato de API, condicionales D1-D4, plan de construcción por fases). |

## Requisitos

- PHP >= 8.1, Composer
- Un sitio Drupal 10.3+ u 11 con MySQL
- Node.js 20.19+ (o 22.12+) y npm, para compilar el frontend

## Instalación — backend

1. Copiar `utp_nomenclaturas/` a `modules/custom/` del sitio Drupal.
2. Desde la raíz del sitio Drupal, agregar la dependencia declarada en
   [`utp_nomenclaturas/composer.json`](utp_nomenclaturas/composer.json)
   (`phpoffice/phpspreadsheet`, usado para exportar los Excel de
   nomenclaturas/UTMs).
3. Copiar [`.env.example`](.env.example) a `.env` en la raíz del proyecto
   Drupal y completar los valores reales (ver [Variables de
   entorno](#variables-de-entorno)).
4. Copiar [`settings.env.php`](settings.env.php) a
   `sites/default/settings.env.php`, y agregar al final de
   `sites/default/settings.php`:
   ```php
   require DRUPAL_ROOT . '/sites/default/settings.env.php';
   ```
5. Instalar el módulo:
   ```bash
   drush en utp_nomenclaturas -y
   ```
6. Asignar los permisos correspondientes a cada rol (ver
   [Permisos](#permisos)).

## Instalación — frontend

```bash
cd utp-nomenclaturas-app
npm install
npm run build
```

Copiar el contenido de `utp-nomenclaturas-app/dist/` a
`utp_nomenclaturas/js/dist/` dentro del sitio Drupal instalado. El módulo
lee `js/dist/.vite/manifest.json` en tiempo de ejecución para montar los
archivos con hash de Vite (no se declaran como `library` estática porque
el nombre de archivo cambia en cada build). La SPA queda disponible en
`/admin/utp/nomenclaturas`.

## Variables de entorno

Ver [`.env.example`](.env.example) (backend) y
[`utp-nomenclaturas-app/.env.example`](utp-nomenclaturas-app/.env.example)
(frontend). Resumen del backend:

| Variable | Para qué |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_PREFIX` | Conexión a MySQL |
| `DRUPAL_HASH_SALT` | Salt de seguridad de Drupal (generar con `openssl rand -base64 55`) |
| `TRUSTED_HOST_PATTERNS` | Dominios permitidos, como regex separados por coma |
| `CONFIG_SYNC_DIRECTORY` | Directorio de sync de configuración (`drush cex`/`cim`) |
| `APP_ENV` | `dev` \| `staging` \| `prod` — controla logging y caché |

Si el hosting ya inyecta estas variables (Docker/DDEV/Lando/Kubernetes),
no hace falta un `.env` real ni instalar `vlucas/phpdotenv`.

## Desarrollo del frontend (standalone)

```bash
cd utp-nomenclaturas-app
npm install
npm run dev
```

Corre contra mocks en memoria (MSW) que reproducen el contrato completo de
la API — no necesita un backend real levantado. Otros comandos útiles:

```bash
npm run lint    # ESLint
npm run test    # Vitest
npm run build   # build de producción + manifest.json
```

## Permisos

| Permiso | Para qué |
|---|---|
| `access utp nomenclaturas` | Lectura del árbol de campañas y configuración vía API |
| `edit utp nomenclaturas` | Crear, editar, eliminar y duplicar campañas/conjuntos/anuncios, UTMs manuales, export/import |
| `administer utp nomenclaturas config` | Editar listas del diccionario, condicionales por etapa, pilares por segmento y la matriz Campus×Facultad |

## Testing

- Backend: PHPUnit (Kernel/Unit) en `utp_nomenclaturas/tests/` —
  `vendor/bin/phpunit -c core --group utp_nomenclaturas` (o el runner que
  use el sitio Drupal).
- Frontend: Vitest en `utp-nomenclaturas-app/` — `npm run test`.

## Documentación adicional

`SDD-UTP-Nomenclaturas.md` tiene el detalle completo de dominio, contrato
de API, reglas condicionales (D1-D4) y la ruta de migración desde
`UTP-Nomenclaturas.html`.
