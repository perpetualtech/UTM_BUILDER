# UTP · Constructor de Nomenclaturas

Estandariza la nomenclatura de campañas, conjuntos de anuncios y anuncios
de UTP, y deriva sus parámetros UTM. Sustituye la herramienta de
referencia de archivo único (`UTP-Nomenclaturas.html`, que se conserva en
el repo como fuente de verdad de las reglas de negocio) por un módulo
Drupal con API REST propia y un frontend HTML/CSS/JS sin dependencias.

Ver [`ARQUITECTURA.md`](ARQUITECTURA.md) para el mapeo frontend↔API↔tablas.

## Arquitectura

| Carpeta | Contenido |
|---|---|
| [`utp_nomenclaturas/`](utp_nomenclaturas) | Módulo Drupal 10.3+/11. Entidades de contenido (`Campaign`, `AdSet`, `Ad`, `ManualUtm`) sobre MySQL vía Entity API; diccionario y condicionales como Configuration; API REST bajo `/api/utp-nomenclaturas/v1/*`. |
| [`utp_nomenclaturas/frontend/index.html`](utp_nomenclaturas/frontend/index.html) | Frontend: HTML/CSS/JS sin build, servido por el módulo en `/admin/utp/nomenclaturas`. Mismo layout que `UTP-Nomenclaturas.html`; la capa de persistencia pasa de `localStorage` a `fetch()` contra la API. |
| [`UTP-Nomenclaturas.html`](UTP-Nomenclaturas.html) | Herramienta de referencia original. Fuente de verdad de las reglas de negocio y del layout. |
| [`SDD-UTP-Nomenclaturas.md`](SDD-UTP-Nomenclaturas.md) | Especificación completa: dominio, contrato de API, condicionales D1-D4, esquema de datos, plan de construcción por fases. |

## Flujo de una escritura

```
Navegador                    Drupal (PHP)                    MySQL
┌─────────────────┐         ┌──────────────────────┐         ┌──────┐
│ index.html       │ fetch() │ Controller             │ save() │ tabla │
│ (HTML+CSS+JS)     │──────▶ │  ↓                     │──────▶ │ de la │
│                   │  JSON  │ Service (valida/deriva)│        │entidad│
│                   │◀────── │  ↓                     │◀────── │       │
└─────────────────┘         │ Entity API             │         └──────┘
                             └──────────────────────┘
```

Traza de `addCampaign()` (crear campaña):

```
addCampaign()                                          index.html
  → apiPost('/campaigns', payload)                      apiFetch(), misma capa que el resto de mutaciones
  → POST /api/utp-nomenclaturas/v1/campaigns             header X-CSRF-Token
  → TreeController::createCampaign()                     src/Controller/TreeController.php
  → TreeManager::createCampaign()                        src/Service/TreeManager.php
      valida (§3.2/§3.3), deriva el nombre, $campaign->save()
  → 201 + entidad persistida                              addCampaign() actualiza el estado local con la respuesta
```

Violaciones de negocio responden 422 (condicional inválida) o 409
(nombre duplicado); el `catch()` de cada función las muestra como aviso,
sin persistencia parcial. El resto de las acciones (editar, eliminar,
duplicar, Configuración, UTMs manuales, export/import) siguen el mismo
patrón — cada función de `index.html` documenta su endpoint.

### Tablas

4 tablas MySQL (esquema completo en
[SDD §6](SDD-UTP-Nomenclaturas.md#6-esquema-de-base-de-datos)):

| Tabla | Contenido |
|---|---|
| `campaign` | Campañas |
| `ad_set` | Conjuntos de anuncios (FK `campaign_id`) |
| `ad` | Anuncios (FK `ad_set_id`) |
| `manual_utm` | UTMs manuales |

Diccionario, condicionales D1-D4 y config de UTM viven en Configuration
(`utp_nomenclaturas.dictionary`, `utp_nomenclaturas.utm_config`), no en
tablas — mapeo en [SDD §6.3](SDD-UTP-Nomenclaturas.md#63-diccionario-y-condicionales--configuration-no-tablas-sql).
Tabla completa acción→persistencia en
[SDD §8.5](SDD-UTP-Nomenclaturas.md#85-qué-acción-del-frontend-escribe-en-qué-tabla).

Verificación de persistencia: crear un registro y recargar sin caché — el
estado proviene de un `GET` a MySQL, no de almacenamiento del cliente.

## Requisitos

- PHP >= 8.1, Composer
- Drupal 10.3+ u 11 con MySQL
- Un navegador — el frontend no requiere Node.js ni build

## El módulo

`utp_nomenclaturas/` es un módulo Drupal estándar — se integra con el
proceso de deployment que ya use el equipo de UTP. Puntos a tener en
cuenta al hacerlo:

- Dependencia de Composer: `phpoffice/phpspreadsheet` (exports de Excel),
  declarada en [`composer.json`](utp_nomenclaturas/composer.json).
- Variables de entorno que lee (ver [`.env.example`](.env.example) y
  [Variables de entorno](#variables-de-entorno)); `settings.env.php` es
  la referencia de cómo levantarlas en `settings.php`, si el hosting no
  las inyecta ya por su cuenta.
- 3 permisos propios (ver [Permisos](#permisos)).
- Ruta admin `/admin/utp/nomenclaturas` — `AppController.php` sirve
  `frontend/index.html` directo, sin paso de build ni instalación aparte
  del propio módulo.

## Variables de entorno

Ver [`.env.example`](.env.example):

| Variable | Uso |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_PREFIX` | Conexión MySQL |
| `DRUPAL_HASH_SALT` | Salt de Drupal (`openssl rand -base64 55`) |
| `TRUSTED_HOST_PATTERNS` | Dominios permitidos, regex separados por coma |
| `CONFIG_SYNC_DIRECTORY` | Directorio de sync (`drush cex`/`cim`) |
| `APP_ENV` | `dev` \| `staging` \| `prod` |

Si el hosting inyecta estas variables (Docker/DDEV/Lando/Kubernetes), no
hace falta `.env` ni `vlucas/phpdotenv`.

## Frontend

`utp_nomenclaturas/frontend/index.html`: CSS y JS inline, sin
dependencias salvo `xlsx.full.min.js` (CDN, generación de Excel en
cliente). Se edita y se recarga; no hay build. La capa de comunicación
con la API (`apiFetch`/`apiGet`/`apiPost`/`apiPut`/`apiPatch`/`apiDelete`)
está al inicio del `<script>`, separada de la lógica de UI.

## Permisos

| Permiso | Alcance |
|---|---|
| `access utp nomenclaturas` | Lectura del árbol y configuración |
| `edit utp nomenclaturas` | CRUD/duplicate de campañas/conjuntos/anuncios, UTMs manuales, export/import |
| `administer utp nomenclaturas config` | Diccionario, condicionales, matriz Campus×Facultad |

## Testing

- Backend: PHPUnit (Kernel/Unit), `utp_nomenclaturas/tests/` —
  `vendor/bin/phpunit -c core --group utp_nomenclaturas`.
- Frontend: sin suite (HTML/JS sin framework). Verificado contra un
  servidor de referencia que implementa el mismo contrato REST: CRUD,
  Configuración y UTMs manuales persisten y sobreviven a reload completo;
  export/import es idempotente.

## Documentación adicional

- [`ARQUITECTURA.md`](ARQUITECTURA.md) — mapeo frontend↔API↔tablas.
- [`SDD-UTP-Nomenclaturas.md`](SDD-UTP-Nomenclaturas.md) — dominio,
  contrato de API, condicionales (D1-D4), esquema, migración.
