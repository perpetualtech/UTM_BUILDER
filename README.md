# UTP · Constructor de Nomenclaturas

Herramienta para estandarizar la nomenclatura de campañas, conjuntos de
anuncios y anuncios de UTP, y derivar automáticamente sus parámetros UTM.
Reemplaza la herramienta de referencia en un solo archivo HTML (que se
mantiene en el repo como fuente de verdad funcional) por un módulo Drupal
con API REST propia y una página HTML/CSS/JavaScript plano (sin frameworks
ni paso de build) que consume esa API.

## Arquitectura

| Carpeta | Qué es |
|---|---|
| [`utp_nomenclaturas/`](utp_nomenclaturas) | Módulo Drupal 10.3+/11: entidades de contenido (Campaña, Conjunto, Anuncio, UTM manual) guardadas en MySQL, diccionario y reglas de negocio como Configuration API, API REST bajo `/api/utp-nomenclaturas/v1/*`. |
| [`utp_nomenclaturas/js/app/index.html`](utp_nomenclaturas/js/app/index.html) | El frontend completo: un solo archivo HTML + CSS + JavaScript (sin React, sin build), servido directamente por el módulo en `/admin/utp/nomenclaturas`. Es el mismo diseño de `UTP-Nomenclaturas.html`, pero en vez de guardar en `localStorage` hace `fetch()` a la API de arriba. |
| [`UTP-Nomenclaturas.html`](UTP-Nomenclaturas.html) | Herramienta de referencia original (un solo HTML, sin backend, guarda en `localStorage`). Fuente de verdad de las reglas de negocio y punto de partida para migrar datos reales. |
| [`SDD-UTP-Nomenclaturas.md`](SDD-UTP-Nomenclaturas.md) | Especificación completa de arquitectura y reglas de negocio (dominio, contrato de API, condicionales D1-D4, plan de construcción por fases). |

## Cómo se conectan frontend, backend y base de datos

No hay dos aplicaciones separadas: es **una sola página** (`index.html`)
que le habla directo a **un solo módulo PHP** (Drupal), que es el único
que toca MySQL. No hay React, no hay Node en producción, no hay paso de
build para el frontend — se edita el HTML y se recarga la página.

```
Navegador                    Drupal (PHP)                    MySQL
┌─────────────────┐         ┌──────────────────────┐         ┌──────┐
│ index.html       │ fetch() │ Controller (PHP)      │ save() │ tabla │
│ (HTML+CSS+JS)     │──────▶ │  ↓                     │──────▶ │ de la │
│                   │  JSON  │ Service (valida/deriva)│        │entidad│
│                   │◀────── │  ↓                     │◀────── │       │
└─────────────────┘         │ Entity API (Drupal)    │         └──────┘
                             └──────────────────────┘
```

Ejemplo real, línea por línea — qué pasa al hacer clic en **"Agregar
campaña"** en el Constructor:

1. El botón llama a `addCampaign()` en `index.html`.
2. `addCampaign()` llama a `apiPost('/campaigns', {...})` — la "capa de
   comunicación con el backend", un bloque de ~15 funciones al principio
   del `<script>` de `index.html`, comentado en detalle ahí mismo.
3. Esa función hace un `fetch()` real a
   `/api/utp-nomenclaturas/v1/campaigns` (`POST`), con el token CSRF que
   Drupal exige para cualquier escritura.
4. Drupal enruta esa URL (ver
   [`utp_nomenclaturas.routing.yml`](utp_nomenclaturas/utp_nomenclaturas.routing.yml))
   a `TreeController::createCampaign()`
   ([`src/Controller/TreeController.php`](utp_nomenclaturas/src/Controller/TreeController.php)),
   que valida el payload y se lo pasa a
   `TreeManager::createCampaign()`
   ([`src/Service/TreeManager.php`](utp_nomenclaturas/src/Service/TreeManager.php)).
5. `TreeManager` valida las reglas de negocio (el campus existe, el medio
   es válido para la etapa elegida, etc.), deriva el nombre de la campaña,
   y llama a `$campaign->save()` — **esa línea es el `INSERT`/`UPDATE`
   real en la tabla MySQL de la entidad `Campaign`**, generado por Drupal.
6. El controlador responde con JSON (la campaña ya con su `id` real de
   base de datos) y `addCampaign()` usa esa respuesta para actualizar la
   pantalla.

Si el paso 5 falla una regla de negocio, PHP responde con un error (422 si
falta un dato o viola una condicional, 409 si el nombre ya existe) y el
`catch()` de cada función lo muestra como un aviso en pantalla — nunca se
guarda nada a medias. Todas las demás acciones (editar, eliminar,
duplicar, editar el diccionario en Configuración, UTMs manuales,
exportar/restaurar backup) siguen exactamente el mismo patrón; cada una
está comentada en `index.html` con qué endpoint llama.

**Cómo comprobar que persiste de verdad:** crear una campaña y recargar la
página (F5). Si siguiera en pantalla por `localStorage`, sobreviviría en
ese navegador pero desaparecería en otro. Acá desaparece todo el estado de
JavaScript en cada recarga — lo que se ve después de recargar viene 100%
de un `GET` a la base de datos, no del navegador.

## Requisitos

- PHP >= 8.1, Composer
- Un sitio Drupal 10.3+ u 11 con MySQL
- Un navegador. Nada de Node.js hace falta para usar la herramienta — el
  frontend es HTML/CSS/JS plano, no requiere compilar nada.

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

La página del builder queda disponible en `/admin/utp/nomenclaturas` — no
hay un paso de "instalación de frontend" aparte: `js/app/index.html` ya
está en el módulo, `AppController.php` lo sirve directo.

## Variables de entorno

Ver [`.env.example`](.env.example). Resumen:

| Variable | Para qué |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_PREFIX` | Conexión a MySQL |
| `DRUPAL_HASH_SALT` | Salt de seguridad de Drupal (generar con `openssl rand -base64 55`) |
| `TRUSTED_HOST_PATTERNS` | Dominios permitidos, como regex separados por coma |
| `CONFIG_SYNC_DIRECTORY` | Directorio de sync de configuración (`drush cex`/`cim`) |
| `APP_ENV` | `dev` \| `staging` \| `prod` — controla logging y caché |

Si el hosting ya inyecta estas variables (Docker/DDEV/Lando/Kubernetes),
no hace falta un `.env` real ni instalar `vlucas/phpdotenv`.

## Editar el frontend

`utp_nomenclaturas/js/app/index.html` es un archivo autocontenido (CSS en
`<style>`, JS en `<script>`, sin dependencias salvo la librería pública
`xlsx.full.min.js` para generar Excel en el navegador). Para editarlo:
cambiar el archivo y recargar la página — no hay `npm install` ni build.
La "capa de comunicación con el backend" (todas las funciones que hacen
`fetch()`) está agrupada al principio del `<script>`, separada del resto
de la lógica de UI/negocio.

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
- Frontend: sin suite automatizada (es HTML/JS plano, sin framework de
  testing) — se verificó manualmente en navegador real contra un servidor
  Node de referencia que implementa el mismo contrato de API, confirmando
  que crear/editar/eliminar/duplicar, la Configuración y las UTMs
  manuales persisten de verdad y sobreviven a un reload completo de la
  página.

## Documentación adicional

`SDD-UTP-Nomenclaturas.md` tiene el detalle completo de dominio, contrato
de API, reglas condicionales (D1-D4) y la ruta de migración desde
`UTP-Nomenclaturas.html`.
