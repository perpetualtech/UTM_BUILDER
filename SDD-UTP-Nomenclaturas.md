# SDD – UTP · Constructor de Nomenclaturas
### Spec-Driven Development para reconstrucción full-stack y despliegue en Drupal

> **Cómo leer este documento.**
> Este documento describe cómo el *Constructor de Nomenclaturas* pasa de ser una herramienta en un solo archivo HTML (`UTP-Nomenclaturas.html`, la que UTP ya usa y valida hoy) a una solución full-stack sobre Drupal, sin cambiar ninguna regla de negocio en el camino: el HTML sigue siendo la *fuente de verdad funcional* — sus reglas y su diccionario ya están aprobados por UTP — y este `.md` documenta la arquitectura destino y cómo se llega a ella.
>
> Se organiza en tres bloques: **contexto y decisiones de arquitectura** (§0-1), **especificación técnica** — dominio, reglas de negocio, esquema de base de datos, API, frontend e integración con Drupal (§2-9) —, y **plan de entrega**, con la ruta de migración de datos y las fases de construcción junto con sus criterios de aceptación (§10-12). Los Anexos incluyen los datos semilla, constantes y paleta de diseño necesarios para implementar cada fase sin tener que volver al HTML original.

---

## 0. Contexto y objetivo

| | |
|---|---|
| **Qué existía ANTES de este proyecto** (`UTP-Nomenclaturas.html`, el archivo de referencia) | SPA vanilla (HTML/CSS/JS) autónoma, con persistencia en `localStorage`. Un solo archivo. Sin backend, sin multiusuario, sin gobierno de datos. |
| **Qué se construyó en este proyecto** | Backend real: módulo Drupal con 4 tablas MySQL propias (`campaign`, `ad_set`, `ad`, `manual_utm`, ver §6) + API REST (§7). Frontend: HTML/CSS/JS (§8) que ya **no usa `localStorage` para nada de negocio** — cada acción (crear/editar/eliminar/duplicar/exportar) hace un `fetch()` a esa API, que es la que escribe en MySQL. Ver el mapeo completo en §8.5. |
| **Destino final** | El cliente (UTP) lo **monta en Drupal** (stack Drupal / PHP / MySQL). |
| **Alcance de este spec** | Arquitectura frontend, arquitectura backend, esquema de tablas y dependencias, contrato de API, lógica canónica de naming y UTM, integración Drupal, migración y plan de construcción por fases. |

**Regla de oro:** la lógica de negocio (derivación de nombres, condicionales, derivación de UTMs) es **idéntica** a la del HTML. No se reinventa; se porta y se centraliza en el backend.

---

## 1. Decisión de arquitectura (ADR resumida)

### ADR-001 · Un único módulo Drupal (`utp_nomenclaturas`) — datos + API + frontend
- **Datos:** content entities en MySQL vía Entity API (§6): `campaign`, `ad_set`, `ad`, `manual_utm`. Diccionario y condicionales como Configuration (§9.2).
- **API:** REST propia bajo `/api/utp-nomenclaturas/v1/*` (§7).
- **Frontend:** una página HTML/CSS/JS servida por el mismo módulo (§8, ADR-004).

Todo vive en un solo `drush en utp_nomenclaturas`, sin servidor aparte ni CORS entre dominios.

### ADR-002 · Los nombres son **snapshots inmutables**
El nombre de campaña/conjunto/anuncio se deriva de los códigos seleccionados **en el momento de crear**. Si luego se edita/elimina un valor del diccionario, los nombres históricos **no cambian**. → En el esquema, los códigos seleccionados se **almacenan como strings** en la fila del registro (denormalizado), y se **validan** contra el diccionario en *write-time* (no hay FK dura que rompa históricos). Esto es una dependencia crítica, no un detalle.

### ADR-003 · Derivación canónica en el backend
`NameBuilder` y `UtmDeriver` viven en el backend (PHP service). El frontend puede replicar el `slug()` para preview instantáneo, pero **el servidor es autoritativo** al escribir. Evita divergencia de reglas entre cliente y servidor.

### ADR-004 · Stack frontend — **HTML/CSS/JavaScript plano, sin framework**
*Revisado tras la primera entrega:* el equipo de UTP que va a mantener esta herramienta no trabaja con React, así que un stack con build propio (npm, TypeScript, bundler) es un costo de mantenimiento que no aporta valor acá. El frontend es **un solo archivo HTML** (`utp_nomenclaturas/frontend/index.html`, mismo patrón que `UTP-Nomenclaturas.html`, la herramienta que UTP ya usa hoy), con `fetch()` real contra la API del §7 en vez de `localStorage`. Se edita y se recarga — no hay `npm install` ni paso de build. La lógica de negocio sigue siendo autoritativa en el backend (ADR-003); el frontend solo hace preview y llama a la API.

---

## 2. Modelo de dominio

### 2.1 Jerarquía

```
Pilar (dimensión de Campaña, no entidad propia)
  └─ Campaña        (campaign)
       └─ Conjunto  (ad_set / "Conjunto de Anuncios")
            └─ Anuncio (ad)
```

Más un módulo lateral: **UTM manual** (`manual_utm`) para tráfico sin pauta (influencers/orgánico), no acoplado al árbol.

### 2.2 Atributos por entidad (los que componen el nombre en **orden**)

| Entidad | Campos ordenados que forman el nombre | Campos extra (no forman nombre) |
|---|---|---|
| **Campaña** | `segmento` · `etapa` · `campus` · `medio` · `objCamp` · `objPlat` · `tipoCamp` · **`pilar`** (se agrega al final) | — |
| **Conjunto** | `edad` · `ubicacion` · `facultad` · `senal` · `detalle` | — |
| **Anuncio** | `formato` · `nombre`† · `motivo` · `mensaje` · `carrera` · `fecha` | **`url`** (destino natural; se guarda y hereda al siguiente anuncio, **no** entra al nombre) |

> `detalle` (conjunto) y `mensaje` (anuncio) admiten **valor libre** además de la lista (datalist / campo "Personalizar"). El resto son selects cerrados sobre el diccionario.
>
> † `nombre` es el término de negocio (lista del diccionario, campo del frontend `data-a="nombre"`); en la tabla `ad` la columna se llama `concepto` — ver §6.2/§7.2. Mismo dato, dos nombres por contexto.

---

## 3. Reglas de negocio canónicas (el corazón del spec)

### 3.1 Derivación del nombre

```
slug(v):
  1. trim
  2. normalizar Unicode NFD y remover diacríticos  (á→a, ñ→n conserva? — NFD separa el tilde de la n: "ñ"→"n")
  3. reemplazar 1+ espacios por "-"
  (NO se fuerza minúsculas; los valores del diccionario ya vienen normalizados)

name(entidad):
  tomar los campos ORDENADOS de la tabla 2.2
  descartar los vacíos
  aplicar slug() a cada uno
  unir con separador "_"
```

- **Separador de segmentos:** `_`
- **Campaña:** `slug(segmento)_slug(etapa)_slug(campus)_slug(medio)_slug(objCamp)_slug(objPlat)_slug(tipoCamp)_slug(pilar)`
- **Conjunto:** `slug(edad)_slug(ubicacion)_slug(facultad)_slug(senal)_slug(detalle)`
- **Anuncio:** `slug(formato)_slug(nombre)_slug(motivo)_slug(mensaje)_slug(carrera)_slug(fecha)`

### 3.2 Condicionales (dependencias entre campos) — **especificación exacta**

**D1 · `etapa` → (`medio`, `objCamp`, `objPlat`, `tipoCamp`).**
Estos 4 selects se pueblan **solo** con los valores de la etapa elegida. Sin etapa seleccionada, quedan **deshabilitados y vacíos**. Ver seed en §5.

**D2 · `segmento` → `pilar`.**
Los pilares disponibles dependen del segmento (`adultos` vs `jovenes`). El pilar **`empleabilidad` es exclusivo de `jovenes`**. Regla de UI: si el usuario elige el pilar `empleabilidad`, se fuerza `segmento = jovenes` y se deshabilita el toggle `adultos`.

**D3 · `ubicacion` → `facultad`.**
Una facultad con restricción de sede (`campus_facultad`) solo está disponible si:
- la `ubicacion` elegida está en su lista de sedes permitidas, **o**
- la `ubicacion` es un *grupo* (`lima`, `lideres`, `def-chall`, `virtual`) cuyos miembros (`ubicacion_group`) intersectan las sedes permitidas.
Facultad **sin** restricción → disponible en cualquier ubicación.
Restricciones actuales (seed): `com → [lima-centro, lima-norte]`, `med → [lima-centro, arequipa, chiclayo]`. El resto sin restricción.
Al cambiar `ubicacion`: recalcular opciones de `facultad`; si la facultad ya elegida deja de ser válida, limpiarla y avisar (`toast`). Mostrar hint ✓/⚠ según validez.

**D4 · Matriz Campus × Facultad (Configuración Nivel 2).**
Editable por el usuario. Marca/desmarca qué facultad se ofrece en cada **sede específica** (`SEDES_ESPECIFICAS`, §5). Semántica: si una facultad queda ofrecida en **todas** las sedes específicas, se **elimina** su restricción (pasa a "sin restricción"). Los cambios impactan D3 en tiempo real.

### 3.3 Restricciones de unicidad (constraints)

| Nivel | Constraint |
|---|---|
| Campaña | `name` **único dentro del mismo `pilar`** |
| Conjunto | `name` **único dentro de la misma campaña** |
| Anuncio | `name` **único dentro del mismo conjunto** |
| UTM manual | `utm_source` y `utm_medium` **obligatorios**; validar sources reservados |

Borrado en cascada: eliminar Campaña → sus conjuntos y anuncios; eliminar Conjunto → sus anuncios.

### 3.4 Derivación de UTMs (`deriveUtm`) — **especificación exacta**

1. **Elegir preset por `medio`** (`MEDIO_TO_PRESET`, §5). Para `GoogleAds`, sub-preset por `tipoCamp` (`googleSub`):
   - contiene `pmax`/`performance` → `google-pmax`
   - contiene `demand` → `google-demandgen`
   - contiene `video`/`youtube` → `google-video`
   - contiene `display` → `google-display`
   - si no → `google-search`
2. **Valores de `campaign`/`term`/`content` según plataforma:**
   - **Meta / Tiktok:** dependen de `utmCfg.metaMode`:
     - `macro` → usar las macros del preset (`{{campaign.name}}`, `{{adset.name}}`, `{{ad.name}}` para Meta; `__CAMPAIGN_NAME__`, `__AID_NAME__`, `__CID_NAME__` para Tiktok).
     - `hard` → hardcodear los nombres reales: `campaign=c.name`, `term=g.name`, `content=a.name`.
   - **LinkedIn:** hardcodear nombres reales (`c.name`, `g.name`, `a.name`).
   - **Google / DV360:** `campaign = c.name` (hard); `term` y `content` = macros del preset.
3. **Ensamblado de parámetros:** `utm_source`, `utm_medium`, `utm_campaign`, `utm_term` (si existe), `utm_content` (si existe). Unir con `&`.
4. **Dónde se pegan (`PLAT_PASTE`):** `sep=true` → parámetros en **campo aparte** (URL limpia); `sep=false` (solo **DV360**) → URL + parámetros **juntos**.
5. **URL destino:** `ad.url` → si vacío, `utmCfg.defaultUrl` → si vacío, sin URL (mostrar aviso).
6. **Canal GA4** por preset (`Paid Social` / `Paid Search` / `Display` / `Video` / `Cross-network`) → informativo, para el export.

**UTM manual:** limpieza opcional (`clean`: lowercase, sin diacríticos, espacios→`-`). `source`+`medium` obligatorios. Warnings: sources reservados (`meta`, `fb`, `google-pmax`, `gads`, `demandgen`, `pmax`) fragmentan GA4; falta de source/medium impide clasificación de canal. URL final **completa** (para bio/enlace del influencer).

### 3.5 Export

- **Excel de nomenclaturas:** **una hoja por plataforma** (orden `PLATFORMS`: Meta, Tiktok, DV360, LinkedIn, GoogleAds). Columnas: `Medio` · `Nombre de Campaña` · `Conjunto de Anuncios` · `Anuncio` · `URL de destino` · `Parámetros UTM (copiar/pegar)` · `Dónde pegar`. El **nombre de campaña solo aparece en la primera fila** de cada campaña (idem conjunto). Campañas/conjuntos vacíos igual generan fila.
- **Excel de UTMs:** una hoja por `utm_source` + hoja `Consolidado`. Incluye paid (derivadas) + manuales.
- **Backup/restore JSON:** exporta/importa el árbol completo de campañas.

---

## 4. KPIs (dashboard "Inicio")

`campañas` = total; `conjuntos` = Σ conjuntos; `anuncios` = Σ anuncios; `plataformas` = nº de `medio` distintos en uso. Recalcular en cada mutación.

---

## 5. Diccionario y seed data

Toda esta data debe **sembrarse** exacta en la base (fase 0). El bloque JSON completo y copiable está en **Anexo A**. Resumen estructural:

- **Listas planas** (editables en Config): `segmento`, `etapa`, `campus`, `edad`, `ubicacion`, `facultad`, `senal`, `detalle`, `formato`, `nombre`, `motivo`, `mensaje`, `carrera`, `fecha`.
- **Listas condicionadas por `etapa`**: `medio`, `objCamp`, `objPlat`, `tipoCamp`.
- **Lista condicionada por `segmento`**: `pilar`.
- **Mapas de relación**: `facultadNombre` (labels), `campusFacultad` (restricción facultad→sedes), `ubicacionGrupo` (grupo→sedes miembro), `SEDE_GRUPO` (sede→grupo).
- **Constantes UTM**: `UTM_PRESETS`, `MEDIO_TO_PRESET`, `PLAT_PASTE`, `UX_SOURCES`, `UX_MEDIUMS`, `RESERVED_SRC`.

---

## 6. Esquema de base de datos

MySQL/MariaDB (nativo Drupal). Se presenta **relacional puro** (para claridad de dependencias) y luego el **mapeo a Drupal** (§9). Todas las tablas: `id BIGINT AUTO_INCREMENT PK`, `uuid CHAR(36) UNIQUE`, timestamps.

### 6.1 Diagrama ER

> Los recuadros `DICT_*`/`ETAPA_OPTION`/`SEGMENTO_PILAR`/`FACULTAD_SEDE`/`UBICACION_GROUP` son el **modelo conceptual** (para entender las relaciones) — en la implementación real **no son tablas SQL**, es un solo objeto de Configuration de Drupal. Ver §6.3 para el mapeo exacto. `CAMPAIGN`/`AD_SET`/`AD`/`MANUAL_UTM` sí son tablas MySQL reales (§6.2); `UTM_CONFIG` tampoco es tabla (nota en §6.2).

```mermaid
erDiagram
    DICT_LIST ||--o{ DICT_VALUE : contiene
    DICT_VALUE ||--o{ ETAPA_OPTION : "etapa base"
    DICT_VALUE ||--o{ SEGMENTO_PILAR : "segmento base"
    DICT_VALUE ||--o{ FACULTAD_SEDE : "facultad restringida"
    DICT_VALUE ||--o{ UBICACION_GROUP : "grupo base"

    CAMPAIGN ||--o{ AD_SET : tiene
    AD_SET   ||--o{ AD : tiene

    UTM_CONFIG ||..|| CAMPAIGN : "afecta derivación"
    MANUAL_UTM }o..o{ DICT_VALUE : "source/medium sugeridos"

    CAMPAIGN {
      bigint id PK
      string uuid
      string pillar_code
      string name
      string segmento
      string etapa
      string campus
      string medio
      string obj_camp
      string obj_plat
      string tipo_camp
      datetime created_at
      datetime updated_at
      int created_by
    }
    AD_SET {
      bigint id PK
      string uuid
      bigint campaign_id FK
      string name
      string edad
      string ubicacion
      string facultad
      string senal
      string detalle
      int weight
    }
    AD {
      bigint id PK
      string uuid
      bigint ad_set_id FK
      string name
      string formato
      string concepto
      string motivo
      string mensaje
      string carrera
      string fecha
      string url
      int weight
    }
    MANUAL_UTM {
      bigint id PK
      string uuid
      string utm_source
      string utm_medium
      string utm_campaign
      string utm_term
      string utm_content
      string url
      text qs
    }
    UTM_CONFIG {
      bigint id PK
      string default_url
      enum meta_mode
    }
    DICT_LIST {
      string list_key PK
      string label
      string level
      bool editable
    }
    DICT_VALUE {
      bigint id PK
      string list_key FK
      string value_code
      string value_label
      int sort_order
      bool is_active
    }
    ETAPA_OPTION {
      bigint id PK
      string etapa_code FK
      string field_key
      string option_code
      int sort_order
    }
    SEGMENTO_PILAR {
      bigint id PK
      string segmento_code FK
      string pilar_code
      int sort_order
    }
    FACULTAD_SEDE {
      bigint id PK
      string facultad_code FK
      string sede_code
    }
    UBICACION_GROUP {
      bigint id PK
      string group_code FK
      string member_sede_code
    }
```

### 6.2 Las 4 tablas MySQL reales (esto SÍ existe en la base de datos)

Estas 4 son tablas de verdad, creadas por Drupal al instalar el módulo (Entity API, a partir de `src/Entity/*.php`) — cada `INSERT`/`UPDATE`/`DELETE` que hace el frontend termina en una de estas filas:

```sql
-- ── CAMPAÑA — tabla `campaign` ─────────────────────────────────
CREATE TABLE campaign (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  uuid         CHAR(36) NOT NULL UNIQUE,      -- identidad que usa la API/el frontend
  pillar_code  VARCHAR(64)  NOT NULL,
  name         VARCHAR(512) NOT NULL,        -- nombre derivado, snapshot inmutable (ADR-002)
  segmento     VARCHAR(64), etapa VARCHAR(64), campus VARCHAR(64),
  medio        VARCHAR(64), obj_camp VARCHAR(64), obj_plat VARCHAR(64), tipo_camp VARCHAR(64),
  uid          INT NULL,                      -- usuario Drupal que la creó
  created      INT NOT NULL, changed INT NOT NULL,   -- timestamps Unix (estándar Drupal)
  UNIQUE KEY uq_campaign_pillar_name (pillar_code, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CONJUNTO — tabla `ad_set` ──────────────────────────────────
CREATE TABLE ad_set (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  uuid        CHAR(36) NOT NULL UNIQUE,
  campaign_id BIGINT NOT NULL,                -- FK real a campaign.id, cascada de borrado
  name        VARCHAR(512) NOT NULL,
  edad VARCHAR(64), ubicacion VARCHAR(64), facultad VARCHAR(64), senal VARCHAR(64), detalle VARCHAR(255),
  weight      INT NOT NULL DEFAULT 0,
  created     INT NOT NULL, changed INT NOT NULL,
  CONSTRAINT fk_adset_campaign FOREIGN KEY (campaign_id) REFERENCES campaign(id) ON DELETE CASCADE,
  UNIQUE KEY uq_adset_camp_name (campaign_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ANUNCIO — tabla `ad` ───────────────────────────────────────
CREATE TABLE ad (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  uuid       CHAR(36) NOT NULL UNIQUE,
  ad_set_id  BIGINT NOT NULL,                 -- FK real a ad_set.id, cascada de borrado
  name       VARCHAR(512) NOT NULL,
  formato VARCHAR(64), concepto VARCHAR(128), motivo VARCHAR(64),
  mensaje VARCHAR(255), carrera VARCHAR(64), fecha VARCHAR(16),
  url        VARCHAR(1024) NULL,              -- NO forma parte del nombre
  weight     INT NOT NULL DEFAULT 0,
  created    INT NOT NULL, changed INT NOT NULL,
  CONSTRAINT fk_ad_adset FOREIGN KEY (ad_set_id) REFERENCES ad_set(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ad_set_name (ad_set_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── UTM MANUAL — tabla `manual_utm` ────────────────────────────
CREATE TABLE manual_utm (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE,
  utm_source VARCHAR(128) NOT NULL, utm_medium VARCHAR(128) NOT NULL,
  utm_campaign VARCHAR(255), utm_term VARCHAR(255), utm_content VARCHAR(255),
  url VARCHAR(1024) NOT NULL, qs TEXT NOT NULL,
  uid INT NULL, created INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> `utm_config` (default_url, meta_mode) **tampoco es una tabla** — es otro objeto de Configuration (`utp_nomenclaturas.utm_config`), igual razón que el diccionario en §6.3: es un singleton que conviene versionar con el módulo, no un registro transaccional.

> **Nota de dependencia (ADR-002):** los campos de código en `campaign`/`ad_set`/`ad` **no** llevan FK dura hacia el diccionario. Son snapshots. La validación de que un código existe y está activo se hace en el **service de escritura** (`TreeManager.php`), no en la capa DB — así un nombre histórico nunca cambia aunque el diccionario se edite después.

### 6.3 Diccionario y condicionales — Configuration, NO tablas SQL

A diferencia de §6.2, el diccionario (segmento, campus, edad, facultad… y las condicionales D1-D4) **no vive en tablas separadas**. Se implementó como **un solo objeto de Configuration de Drupal** (`utp_nomenclaturas.dictionary`, YAML versionado en `config/install/`), leído/escrito por `DictionaryProvider.php`. Es una decisión de arquitectura, no un pendiente: viaja versionado con el módulo y se exporta con `drush cex`, algo que un set de tablas normalizadas no da gratis.

En la práctica, todo lo que en un modelo relacional serían filas de `dict_value`/`etapa_option`/`segmento_pilar`/`facultad_sede` es, acá, una clave dentro de ese único YAML:

| Si fuera tabla relacional... | ...en este sistema es esta clave del YAML |
|---|---|
| `dict_value` (una fila por valor de cada lista) | `lists.<segmento\|campus\|edad\|...>` (array) |
| `etapa_option` (etapa → medio/objCamp/objPlat/tipoCamp) | `etapa_conditionals.<campo>.<etapa>` (array) |
| `segmento_pilar` | `segmento_pilar.<segmento>` (array) |
| `facultad_sede` (restricción D3/D4) | `campus_facultad.<facultad>` (array de sedes permitidas) |
| `ubicacion_group` | `ubicacion_grupo.<grupo>` (array de sedes miembro) |

Editar esto desde Configuración (Nivel 1/2/3 en el frontend) llama a `ConfigController.php`, que muta ese mismo objeto de Configuration vía `DictionaryProvider::addListValue()/updateEtapaOptions()/updateSegmentoPilar()/updateCampusFacultad()` — Drupal invalida su caché solo al guardar, así que el cambio se refleja de inmediato en el siguiente `GET /config`. **Si en algún momento se prefiere tener esto como tablas SQL de verdad** (por ejemplo, para hacer reportería con SQL directo), es un cambio de arquitectura documentado y acotado — no algo que ya esté a medio camino.

---

## 7. Contrato de API

Base: `/api/utp-nomenclaturas/v1`. Auth: sesión Drupal; toda ruta no-GET exige header `X-CSRF-Token`. Respuestas JSON; errores `{error, code, details}` (422 condicional inválida, 409 nombre duplicado, 404 no encontrado). Contrato completo y autoritativo:
[`utp_nomenclaturas.routing.yml`](utp_nomenclaturas/utp_nomenclaturas.routing.yml) (32 rutas) — lo que sigue es su resumen legible.

### 7.1 Diccionario y configuración
| Método | Ruta | Controller | Descripción |
|---|---|---|---|
| `GET` | `/config` | `UtmConfigController::getConfig` | Bundle completo (§9.2): listas + condicionales + mapas + presets UTM. Un solo fetch para hidratar el frontend. |
| `GET` | `/config/lists/{list_key}` | `ConfigController::getListValues` | Valores de una lista. |
| `POST` | `/config/lists/{list_key}/values` | `ConfigController::addListValue` | Agrega un valor. Body `{value}`. |
| `DELETE` | `/config/lists/{list_key}/values/{code}` | `ConfigController::deleteListValue` | Quita un valor. |
| `PUT` | `/config/etapa-options/{etapa}/{field}` | `ConfigController::updateEtapaOptions` | Reemplaza el array de opciones (D1). Body `{values}`. |
| `PUT` | `/config/segmento-pilar/{segmento}` | `ConfigController::updateSegmentoPilar` | Reemplaza pilares del segmento (D2). Body `{pilares}`. |
| `PUT` | `/config/campus-facultad` | `ConfigController::updateCampusFacultad` | Reemplaza la matriz D4. Body `{matrix}`. |
| `GET` | `/utms/config` | `UtmController::getUtmConfig` | `{default_url, meta_mode}`. |
| `PATCH` | `/utms/config` | `UtmController::updateUtmConfig` | Actualiza `{default_url, meta_mode}`. |

### 7.2 Árbol (campañas/conjuntos/anuncios)
| Método | Ruta | Controller | Descripción |
|---|---|---|---|
| `GET` | `/campaigns?pillar=&medio=&q=` | `TreeController::listCampaigns` | Lista con `ad_sets_count`/`ads_count`. |
| `GET` | `/campaigns/{uuid}?include=ad_sets,ads` | `TreeController::getCampaignTree` | Árbol completo de una campaña. |
| `POST` | `/campaigns` | `::createCampaign` | Body `{pillar_code, meta}`. El server **deriva `name`** (`NameBuilder`), valida D1-D3 y unicidad. |
| `PATCH` `DELETE` | `/campaigns/{uuid}` | `::updateCampaign` / `::deleteCampaign` | `PATCH` con `{name}` = override manual; con `{meta}` = re-deriva. `DELETE` cascada (`ad_set`→`ad`). |
| `POST` | `/campaigns/{uuid}/duplicate` | `::duplicateCampaign` | Copia el subárbol completo; desambigua nombre con sufijo `-copia`. |
| `POST` | `/campaigns/{uuid}/ad-sets` | `::createAdSet` | Idem conjunto (meta: `edad`/`ubicacion`/`facultad`/`senal`/`detalle`). |
| `PATCH` `DELETE` | `/ad-sets/{uuid}` | `::updateAdSet` / `::deleteAdSet` | — |
| `POST` | `/ad-sets/{uuid}/duplicate` | `::duplicateAdSet` | — |
| `POST` | `/ad-sets/{uuid}/ads` | `::createAd` | Body `{meta, url}` (meta: `formato`/`concepto`/`motivo`/`mensaje`/`carrera`/`fecha`). |
| `PATCH` `DELETE` | `/ads/{uuid}` | `::updateAd` / `::deleteAd` | `PATCH` acepta `{url}` solo (no forma parte del nombre). |
| `POST` | `/ads/{uuid}/duplicate` | `::duplicateAd` | — |

### 7.3 UTMs y export/import
| Método | Ruta | Controller | Descripción |
|---|---|---|---|
| `GET` | `/utms/paid` | `UtmController::listPaid` | Todas las UTMs derivadas, ya aplanadas (§3.4); filtro por medio/pilar/búsqueda se hace en el cliente. |
| `GET` | `/utms/manual` | `::listManual` | — |
| `POST` | `/utms/manual` | `::createManual` | Body `{utm_source, utm_medium, utm_campaign?, utm_term?, utm_content?, url, qs}`. |
| `DELETE` | `/utms/manual/{uuid}` | `::deleteManual` | — |
| `GET` | `/export/campaigns.xlsx?pillar=&medio=&q=&uuids[]=` | `ExportController::exportCampaigns` | Excel, una hoja por plataforma (§3.5). `uuids[]` fija selección explícita. |
| `GET` | `/export/utms.xlsx` | `::exportUtms` | Excel UTMs: una hoja por `utm_source` + Consolidado. |
| `GET` | `/export/backup.json` | `::exportBackup` | Backup del árbol, shape propio (uuid-based). |
| `POST` | `/import` | `::importBackup` | Restaura backup — acepta shape propio **y** el legacy de `UTP-Nomenclaturas.html` (§10). Idempotente por `uuid`. |

---

## 8. Arquitectura frontend

### 8.1 Stack y principios (revisado — ver ADR-004)
- **Un solo archivo HTML/CSS/JavaScript, sin framework ni build.** `utp_nomenclaturas/frontend/index.html`: mismo layout/CSS que `UTP-Nomenclaturas.html` (la herramienta que UTP ya conoce), con el `<script>` reescrito para hablar con la API del §7 en vez de `localStorage`.
- **Cero dependencias de paquetes** salvo `xlsx.full.min.js` (CDN), usado solo para generar los `.xlsx` en el navegador — igual que en la herramienta original.
- **Sin `localStorage` para datos de negocio.** El estado en memoria (`campaigns`, `D`, `utmStore`, `utmCfg`) es una copia de lo último que respondió el servidor; se reconstruye desde cero en cada carga de página vía `fetch()`.
- **El servidor sigue siendo autoritativo** (ADR-003): el frontend deriva el nombre localmente solo para la vista previa instantánea; la fuente de verdad es la respuesta de la API tras guardar.

### 8.2 La "capa de comunicación con el backend"
Al principio del `<script>` de `index.html` hay un bloque comentado explícitamente para que cualquiera (no solo quien lea código) entienda el camino completo request→respuesta:
- `apiFetch(path, {method, body})` — único punto de contacto con PHP: arma la URL (`API_BASE` + `path`), agrega el header CSRF en mutaciones, parsea la respuesta y convierte errores HTTP en una `ApiError` con el mismo `{error, code, details}` que emite `UtpNomenclaturaExceptionSubscriber.php`.
- `apiGet/apiPost/apiPatch/apiPut/apiDelete` — atajos sobre `apiFetch()` por verbo.
- `API_BASE`/`CSRF_TOKEN` — leídos de `window.UTP_SETTINGS`, que `AppController::app()` (§9.5) inyecta en el HTML al servirlo.

Cada función de negocio (`addCampaign`, `saveEdit`, `delCamp`, `addListItem`, `saveManual`, etc.) es `async`, llama a uno de estos helpers, y actualiza la pantalla **solo** con lo que el servidor confirmó — nunca asume que una escritura tuvo éxito de antemano.

### 8.3 Vistas (sin cambios respecto a `UTP-Nomenclaturas.html`)
Inicio (KPIs) · Constructor (drill-down Pilar▸Campaña▸Conjunto▸Anuncio con preview en vivo) · Repositorio (árbol filtrable + edición inline) · UTMs (paid automático + manual) · Exportar (Excel + backup/restore JSON) · Configuración (Nivel 1/2/3: listas, condicionales D1/D2, matriz D4) · Diccionario (referencia, solo lectura). Cada vista es una `<section class="view">` que se muestra/oculta con CSS; no hay router ni recarga de página al navegar entre ellas.

### 8.4 Mapeo local ↔ servidor
El HTML usa nombres de campo en camelCase e identidad `id` (heredados de la herramienta original); la API responde snake_case e identidad `uuid` (`TreeController.php`). Tres funciones (`campaignFromApi`, `adSetFromApi`, `adFromApi`) traducen una forma a la otra, para no tener que tocar ninguna función de renderizado ya existente.

### 8.5 Qué acción del frontend escribe en qué tabla

La tabla siguiente es la respuesta directa a "¿qué tabla corresponde a qué cosa?" — cada fila es una acción real del `index.html`, con el archivo PHP y la tabla/config exactos que toca.

| Acción en el frontend | Función en `index.html` | Controlador → Service (PHP) | Dónde persiste |
|---|---|---|---|
| "Agregar campaña" (Constructor) | `addCampaign()` | `TreeController::createCampaign` → `TreeManager::createCampaign` | `INSERT` en tabla **`campaign`** (§6.2) |
| "Agregar conjunto" | `addGroup()` | `TreeController::createAdSet` → `TreeManager::createAdSet` | `INSERT` en tabla **`ad_set`**, con `campaign_id` (FK) |
| "Agregar anuncio" | `addAd()` | `TreeController::createAd` → `TreeManager::createAd` | `INSERT` en tabla **`ad`**, con `ad_set_id` (FK) |
| Editar nombre inline (Repositorio/Constructor) | `saveEdit()` | `TreeController::update{Campaign\|AdSet\|Ad}` | `UPDATE` de la fila correspondiente |
| Eliminar (cualquier nivel) | `delCamp/delGroup/delAd` | `TreeController::delete{Campaign\|AdSet\|Ad}` | `DELETE` de la fila; Drupal hace la cascada (`ad_set`→`ad`, `campaign`→`ad_set`) solo, vía `ON_DELETE_CASCADE` |
| Duplicar (cualquier nivel) | `dupCamp/dupGroup/dupAd` | `TreeController::duplicate{Campaign\|AdSet\|Ad}` → `TreeManager` | `INSERT` de una fila nueva por cada nodo copiado |
| URL del anuncio (Nivel 3) | `setAdUrl()` | `TreeController::updateAd` | `UPDATE ad SET url=...` |
| Guardar UTM manual | `saveManual()` | `UtmController::createManual` | `INSERT` en tabla **`manual_utm`** |
| Eliminar UTM manual | `delUtm()` | `UtmController::deleteManual` | `DELETE` en `manual_utm` |
| Cambiar modo macro/hard (UTMs) | toggle en `initUtm()` | `UtmController::updateUtmConfig` | Configuration `utp_nomenclaturas.utm_config` (§6.2) |
| Agregar/quitar valor de una lista (Configuración N1/N3) | `addListItem/removeListItem` | `ConfigController::addListValue/deleteListValue` → `DictionaryProvider` | Configuration `utp_nomenclaturas.dictionary`, clave `lists.<campo>` (§6.3) |
| Editar condicionales por etapa (Configuración N1, D1) | `addEtapaItem/removeEtapaItem` | `ConfigController::updateEtapaOptions` | Configuration, clave `etapa_conditionals.<campo>.<etapa>` |
| Editar pilares por segmento (Configuración N1, D2) | `addPilarItem/removePilarItem` | `ConfigController::updateSegmentoPilar` | Configuration, clave `segmento_pilar.<segmento>` |
| Matriz Campus×Facultad (Configuración N2, D4) | `toggleFacultadAtSede()` | `ConfigController::updateCampusFacultad` | Configuration, clave `campus_facultad.<facultad>` |
| Restaurar backup JSON | import en Exportar | `ExportController::importBackup` → `BackupService::import` | `INSERT`/`UPDATE` en `campaign`/`ad_set`/`ad` (idempotente por `uuid`) |

---

## 9. Integración Drupal (módulo `utp_nomenclaturas`)

### 9.1 Estructura del módulo (tal como está en el repo)
```
utp_nomenclaturas/
  utp_nomenclaturas.info.yml
  utp_nomenclaturas.routing.yml          # 32 rutas, ver §7
  utp_nomenclaturas.permissions.yml      # 3 permisos, ver §9.5
  utp_nomenclaturas.services.yml
  utp_nomenclaturas.install              # hook_requirements/uninstall
  utp_nomenclaturas.module
  composer.json                          # phpoffice/phpspreadsheet
  config/install/                        # diccionario + presets UTM + config UTM (seed, §9.2)
  config/schema/                         # schema de esa Configuration
  src/
    Entity/          Campaign.php AdSet.php Ad.php ManualUtm.php
    Controller/       TreeController.php ConfigController.php UtmController.php
                       UtmConfigController.php ExportController.php AppController.php
    Service/          NameBuilder.php TreeManager.php DictionaryProvider.php
                       UtmDeriver.php ExcelExporter.php BackupService.php
    Exception/        UtpNomenclaturaException.php + 3 subclases (§7)
    EventSubscriber/   UtpNomenclaturaExceptionSubscriber.php
  frontend/index.html                    # frontend (§8, ADR-004)
```
Sin JSON:API ni `RestResource` plugins: los 6 `Controller/*.php` son la API completa. Evita mapear las condicionales D1-D4 (reglas de negocio propias, no CRUD genérico) sobre el contrato estándar de JSON:API.

### 9.2 Persistencia
- **Content entities** para lo transaccional: `Campaign`, `AdSet`, `Ad`, `ManualUtm` → tablas `campaign`/`ad_set`/`ad`/`manual_utm` (§6.2). Relaciones padre-hijo por `entity_reference` + borrado en cascada (`ON_DELETE_CASCADE`) nativo de la Entity API.
- **Configuration** para diccionario y condicionales (`utp_nomenclaturas.dictionary`) y config de UTM (`utp_nomenclaturas.utm_config`) — YAML en `config/install`, editable en runtime vía `ConfigController`/`DictionaryProvider` (§6.3). Viaja versionado con el módulo, exportable con `drush cex`.

### 9.3 Servicios (lógica canónica)
- `NameBuilder::campaignName/adSetName/adName()` → §3.1, único lugar donde vive el naming.
- `TreeManager` → CRUD + duplicate de los 3 niveles: valida (D1-D3), deriva nombre, persiste.
- `DictionaryProvider` → expone el bundle (§9.2), resuelve D1–D4, valida códigos, muta Configuration (Config Nivel 1/2/3).
- `UtmDeriver::derive()` → §3.4, incluye `googleSub`, `metaMode`, `PLAT_PASTE`.
- `ExcelExporter` → **PhpSpreadsheet** (reemplazo server-side de SheetJS). Reglas §3.5.
- `BackupService` → export/import del árbol, acepta el shape propio y el legacy de `UTP-Nomenclaturas.html` (§10).

### 9.4 Contrato REST
6 `Controller/*.php`, sin JSON:API — contrato completo (32 rutas) en
[`utp_nomenclaturas.routing.yml`](utp_nomenclaturas/utp_nomenclaturas.routing.yml)
y detallado en §7/§8.5. Permisos por ruta en
[`utp_nomenclaturas.permissions.yml`](utp_nomenclaturas/utp_nomenclaturas.permissions.yml).

### 9.5 Montaje del frontend + seguridad (revisado — ver ADR-004)
- `AppController::app()` lee `frontend/index.html` como texto y lo devuelve tal cual como `Response` HTML cruda (no un render array de Drupal — la página ya trae su propio `<html><head><style>`, montarla dentro del theme de Drupal duplicaría el documento).
- Antes de devolverlo, inyecta `<script>window.UTP_SETTINGS={apiBase,csrfToken}</script>` justo después de `<body>` — es lo único que el HTML necesita de Drupal; todo el resto (`apiFetch()` y el resto de la "capa de comunicación con el backend", §8.2) vive en el propio archivo.
- Ruta admin: `/admin/utp/nomenclaturas` (permiso `access utp nomenclaturas`).
- `csrfToken` es el mismo que exige el access checker core `_csrf_request_header_token` (`CsrfRequestHeaderAccessCheck`); el frontend lo envía como header `X-CSRF-Token` en toda mutación.
- Permisos: `access utp nomenclaturas` (ver), `edit utp nomenclaturas` (crear/editar/eliminar), `administer utp nomenclaturas config` (diccionarios/matriz).

---

## 10. Migración de datos (localStorage → DB)

El HTML actual guarda en `localStorage`:
- `utp_nomenclaturas_v2` → árbol de campañas (backup JSON ya soportado).
- `utp_config_v1` → overrides de diccionario.
- `utp_utms_v1` → UTMs manuales.
- `utp_utmcfg_v1` → `{defaultUrl, metaMode}`.

**Ruta de migración:** el usuario exporta "Backup JSON" desde el HTML → se sube por `POST /import` (o `drush utp:import backup.json`) → `BackupService` mapea el árbol a entidades, validando §3.2/§3.3. Config y UTMs manuales se importan con endpoints análogos. Idempotente por `uuid`.

---

## 11. Plan de construcción por fases

> En cada fase: **imprimir plan → confirmar → implementar → correr acceptance criteria**. No mezclar fases.

**Fase 0 · Fundaciones (backend)**
- Scaffolding del módulo Drupal + entidades + schema/config.
- **Seed** del diccionario y condicionales desde Anexo A (verbatim).
- *AC:* `drush en utp_nomenclaturas` sin errores; `GET /config` devuelve el bundle completo idéntico al Anexo A.

**Fase 1 · Dominio + API del árbol**
- `NameBuilder`, `DictionaryProvider`, validaciones (§3.2/§3.3). CRUD + duplicate + cascada.
- *AC:* tests unitarios de naming (3 casos por nivel) y de condicionales D1–D4 en verde; 409 en nombre duplicado; 422 en condicional violado.

**Fase 2 · Frontend base**
- Shell + Dashboard + Builder (drill-down, dependent selects, preview, children table) + Repository. Tokens UTP.
- *AC:* crear campaña▸conjunto▸anuncio end-to-end contra la API; nombres idénticos a los del HTML de referencia.
- *Nota:* implementada originalmente en React (§8/ADR-004 de esa primera entrega); reemplazada por completo en la **Fase 5** por HTML/CSS/JS plano — ver ADR-004 vigente y §8.

**Fase 3 · UTM + Export/Import**
- `UtmDeriver` + vistas Paid/Manual; `ExcelExporter` (PhpSpreadsheet) con reglas §3.5; backup/import.
- *AC:* el Excel por plataforma reproduce columnas/orden/celdas-vacías del HTML; UTMs derivadas coinciden campo a campo en macro y hard mode.

**Fase 4 · Config + Diccionario + empaquetado Drupal**
- Config Nivel 1/2/3 + matriz Campus×Facultad editables; Dictionary read-only; permisos, CSRF, ruta admin.
- *AC:* editar la matriz impacta D3 en vivo; módulo instalable en limpio; migración de un backup real del HTML sin pérdida.

**Fase 5 · Frontend sin framework (reemplaza el de la Fase 2)**
- El cliente (UTP) no maneja React — su equipo necesita poder abrir y editar el frontend sin instalar Node/npm ni entender un build. Se reemplaza la SPA React de la Fase 2 por `utp_nomenclaturas/frontend/index.html`: un solo HTML/CSS/JS que cubre las mismas 7 vistas, con `fetch()` real contra la API (§7) en el lugar de `localStorage`. El backend (Fases 0-4) no cambia — es la misma API REST, ahora consumida por HTML plano en vez de React.
- *AC:* crear/editar/eliminar/duplicar campaña▸conjunto▸anuncio persiste y sobrevive a un reload completo de la página (prueba de que ya no depende de `localStorage`); editar Configuración (listas, condicionales, matriz D4) también persiste y sobrevive a un reload; exportar el backup y volver a importarlo no duplica datos (idempotente).

**Gate final:** correr `verificador-produccion-agentica` (Launch Gate) sobre el repo antes de entregar a UTP.

---

## 12. Dependencias técnicas

| Capa | Paquetes |
|---|---|
| Drupal | Drupal 10.3+/11 core (sin `jsonapi`/`rest` — controllers/rutas propios, §9.4), `phpoffice/phpspreadsheet` (composer), `drush` |
| Frontend | Ninguna — HTML/CSS/JavaScript plano (ADR-004); `xlsx.full.min.js` vía CDN solo para generar Excel en el navegador |
| Dev | PHPUnit (kernel/unit para services) |

---

## Anexo A · Seed data (copiable)

```json
{
  "lists": {
    "segmento": ["adultos","jovenes"],
    "etapa": ["upper","middle","lower"],
    "campus": ["lima","lideres","def-chall","virtual"],
    "edad": ["a1","a2","j1","j2","a1-a2","j1-j2"],
    "ubicacion": ["nacional","lima","lideres","def-chall","lima-centro","lima-norte","lima-sur","lima-este-ate","lima-este-sjl","arequipa","chiclayo","iquitos","pucallpa","tacna","piura","chimbote","ica","huancayo","trujillo","no-sedes"],
    "facultad": ["ing","neg","der","psi-edu","com","arq","sal","med","virtual"],
    "senal": ["broad","lal","rmkt","intereses","int-lal","broad-lal","int-rmkt","int-adv","lal-rmkt","int-lal-rmkt"],
    "detalle": ["gaming-tech","bbdd-inscritos-total","views-interacciones","alcance-adultos","views-adultos","alcance-jovenes","views-jovenes","alcance-virtual","visitas-adultos"],
    "formato": ["video","carrusel","ppl","ppv","collection","catalogo","sparkad","rsa","instream","bumper","short","banner","youtube video"],
    "nombre": ["lo-que-el-mar-se-llevo","empleabilidad","departamenos-foco","modalidad","medicina","marca","refuerzo-chiclayo","monarca","solavete","cuando-es-tu-momento","podcast","marca-internacional","pasaporte","tierras-de-cambio"],
    "motivo": ["testimonial","malla","beneficios","lifestyle","horarios","convalidaciones","empleabilidad","chat_asesor","rotacion_carreras","ugc-creator","casos-exito","porque-utp","campus"],
    "mensaje": ["estudia-trabaja","reinventa-carrera","porque-utp","horarios-flex","convalida-termina","completa-solicitud","continua-consulta","estudia-online","cerca-de-ti","jhohanna","dalia","cinthya","tessy","carla","entrevista-carla","mundo-laboral","cosas-que-nadie-dijo","set-grabacion","proximo-episodio","ya-esta-aqui","si-ni-no","episodio1","docentes-profesionales","convenio-internacional","influencer-finanfieras","influencer-diego-poblete","influencer-cristian-arens"],
    "carrera": ["no-carreras","ing-aero","ing-amb","ing-auto","ing-bio","ing-civ","ing-elec","ing-elecypot","ing-empr","ing-ind","ing-mecat","ing-mec","ing-minas","ing-segurind","ing-sisteinf","ing-soft","ing-telecom","admin-banca","admin-empr","admin-negint","admin-hotel","admin-mkt","conta","eco","med","enfer","farm-bioq","labclinico","nutri-diet","obste","odontologia","tecn-med-terapia","psicologia","ciencias-com","com-publi","dis-digitalpubli","dis-graf","arquitectura","dis-int","derecho","edu-inicial","edu-prim","ingenieria","negocios"],
    "fecha": ["ene26","feb26","mar26","abr26","may26","jun26","jul26","ago26","sep26","oct26","nov26","dic26"]
  },
  "etapa_conditionals": {
    "medio": {
      "upper": ["Meta","Tiktok","DV360","LinkedIn","GoogleAds"],
      "middle": ["Meta","Tiktok","DV360","LinkedIn","GoogleAds"],
      "lower": ["Meta","Tiktok","GoogleAds","LinkedIn"]
    },
    "objCamp": {
      "upper": ["Awareness"],
      "middle": ["Tráfico RMKT","Qualifed Traffic","Conversiones"],
      "lower": ["Conversiones","Venta"]
    },
    "objPlat": {
      "upper": ["Alcance","Vistas","Vistas Completas","CPM"],
      "middle": ["Clics","Sesiones","LeadWeb","WPP Convers Inic"],
      "lower": ["LeadWeb","LeadAds","WPP Conversion","Inscritos","WPP Convers Inic"]
    },
    "tipoCamp": {
      "upper": ["Video","Display","Media Unification","Demand-Gen","Youtube"],
      "middle": ["Video","Display","Media Unification","Demand-Gen","Youtube","WPP"],
      "lower": ["Video","Display","Search","PMAX","WPP","LeadAds"]
    }
  },
  "segmento_pilar": {
    "adultos": ["calidad","accesibilidad","orgullo"],
    "jovenes": ["calidad","accesibilidad","orgullo","empleabilidad"]
  },
  "facultadNombre": {
    "ing":"Ingeniería","neg":"Negocios","der":"Derecho","psi-edu":"Psicología / Educación",
    "com":"Comunicaciones","arq":"Arquitectura","sal":"Ciencias de la Salud","med":"Medicina","virtual":"Virtual"
  },
  "campusFacultad": {
    "com": ["lima-centro","lima-norte"],
    "med": ["lima-centro","arequipa","chiclayo"]
  },
  "ubicacionGrupo": {
    "lima": ["lima","lima-centro","lima-norte","lima-sur","lima-este-ate","lima-este-sjl"],
    "lideres": ["lideres","arequipa","chiclayo","iquitos","pucallpa","tacna"],
    "def-chall": ["def-chall","piura","chimbote","ica","huancayo","trujillo"],
    "virtual": ["virtual","no-sedes","nacional"]
  },
  "sedes_especificas": ["lima-centro","lima-norte","lima-sur","lima-este-ate","lima-este-sjl","arequipa","chiclayo","iquitos","pucallpa","tacna","piura","chimbote","ica","huancayo","trujillo"],
  "sede_grupo": {
    "lima-centro":"Lima","lima-norte":"Lima","lima-sur":"Lima","lima-este-ate":"Lima","lima-este-sjl":"Lima",
    "arequipa":"Líderes","chiclayo":"Líderes","iquitos":"Líderes","pucallpa":"Líderes","tacna":"Líderes",
    "piura":"Def & Chall","chimbote":"Def & Chall","ica":"Def & Chall","huancayo":"Def & Chall","trujillo":"Def & Chall"
  },
  "platforms": ["Meta","Tiktok","DV360","LinkedIn","GoogleAds"]
}
```

## Anexo B · Constantes UTM (copiable)

```json
{
  "utm_presets": {
    "meta": {"plat":"Meta","source":"facebook","medium":"cpc","campaign":"{{campaign.name}}","term":"{{adset.name}}","content":"{{ad.name}}","ga4":"Paid Social"},
    "tiktok": {"plat":"Tiktok","source":"tiktok","medium":"cpc","campaign":"__CAMPAIGN_NAME__","term":"__AID_NAME__","content":"__CID_NAME__","ga4":"Paid Social"},
    "google-search": {"plat":"GoogleAds","source":"google","medium":"cpc","campaign":"{campaignid}","term":"{keyword}","content":"{creative}","ga4":"Paid Search"},
    "google-pmax": {"plat":"GoogleAds","source":"google","medium":"cpc","campaign":"{campaignid}","term":"","content":"{resource group}","ga4":"Cross-network"},
    "google-demandgen": {"plat":"GoogleAds","source":"google","medium":"cpc","campaign":"{campaignid}","term":"","content":"{conjuntodeanuncio}","ga4":"Cross-network / Paid"},
    "google-video": {"plat":"GoogleAds","source":"google","medium":"cpv","campaign":"{campaignid}","term":"","content":"{creative}","ga4":"Video"},
    "google-display": {"plat":"GoogleAds","source":"google","medium":"cpc","campaign":"{campaignid}","term":"{placement}","content":"{creative}","ga4":"Display"},
    "dv360": {"plat":"DV360","source":"dv360","medium":"display","campaign":"HARD","term":"${CREATIVE_ID}","content":"${LINE_ITEM_ID}","ga4":"Display"},
    "linkedin": {"plat":"LinkedIn","source":"linkedin","medium":"cpc","campaign":"HARD","term":"HARD","content":"HARD","ga4":"Paid Social"}
  },
  "medio_to_preset": {"Meta":"meta","Tiktok":"tiktok","GoogleAds":"google-search","DV360":"dv360","LinkedIn":"linkedin"},
  "plat_paste": {
    "Meta": {"sep": true,  "where": "Meta › Seguimiento › Parámetros de URL (URL limpia en «URL del sitio web»)"},
    "Tiktok": {"sep": true,  "where": "TikTok › Anuncio › Edit URL parameters (Auto-attach OFF)"},
    "GoogleAds": {"sep": true,  "where": "Google › Sufijo de la URL final"},
    "DV360": {"sep": false, "where": "DV360 › Creative › Landing page URL (URL + parámetros juntos)"},
    "LinkedIn": {"sep": true,  "where": "LinkedIn › Parámetro de URL del anuncio"}
  },
  "ux_sources": ["instagram","tiktok","youtube","facebook","linkedin","whatsapp","newsletter","email","linktree","spotify","blog","influencer"],
  "ux_mediums": ["influencer","social","referral","bio-link","email","organic","affiliate","partnership","cpc","cpm"],
  "reserved_src": ["meta","fb","google-pmax","gads","demandgen","pmax"]
}
```

## Anexo C · Design tokens (paleta UTP)

```css
:root{
  --utp-red:#E4002B; --utp-red-dark:#B80020; --utp-black:#1A1A1A;
  --ink:#1A1A1A; --muted:#6B6B70; --line:#E4E4E8;
  --surface:#FFFFFF; --bg:#F5F5F7; --bg-2:#EFEFF2;
  --ok:#1A8A3C; --warn:#C9620A;
  --shadow:0 1px 3px rgba(0,0,0,.06),0 8px 24px rgba(0,0,0,.05);
}
/* Tipografía: Inter (fallback Segoe UI, Arial). Mono: SFMono/Consolas para nomenclaturas. */
```

## Anexo D · Pseudocódigo derivación UTM (referencia para `UtmDeriver`)

```
function googleSub(tipoCamp):
  t = lower(tipoCamp)
  if t contains "pmax" or "performance": return "google-pmax"
  if t contains "demand": return "google-demandgen"
  if t contains "video" or "youtube": return "google-video"
  if t contains "display": return "google-display"
  return "google-search"

function presetFor(campaign):
  if campaign.medio == "GoogleAds": return UTM_PRESETS[ googleSub(campaign.tipoCamp) ]
  return UTM_PRESETS[ MEDIO_TO_PRESET[ campaign.medio ] ]

function derive(c, g, a, metaMode):
  p = presetFor(c); if !p: return null
  if p.plat in ("Meta","Tiktok"):
     if metaMode == "hard": campaign=c.name; term=g.name; content=a.name   # hard
     else:                  campaign=p.campaign; term=p.term; content=p.content  # macros
  elif p.plat == "LinkedIn": campaign=c.name; term=g.name; content=a.name  # hard
  else: # Google / DV360
     campaign=c.name; term=p.term; content=p.content
  parts = [utm_source=p.source, utm_medium=p.medium, utm_campaign=campaign]
  if term:    parts += utm_term=term
  if content: parts += utm_content=content
  params = join(parts, "&")
  url = a.url or utm_config.default_url or ""
  paste = PLAT_PASTE[p.plat]
  return { plat, source, medium, campaign, term, content, params,
           url, full: joinUrl(url, params), sep: paste.sep, where: paste.where, ga4: p.ga4 }
```

---

*Fin del SDD. El artefacto `UTP-Nomenclaturas.html` acompaña este documento como especificación funcional de referencia; ante cualquier duda de comportamiento, el HTML manda.*
