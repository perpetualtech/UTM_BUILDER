# Arquitectura: frontend, API, persistencia

Mapeo frontend↔API↔base de datos. Para dominio, contrato completo y
condicionales de negocio, ver `SDD-UTP-Nomenclaturas.md`.

## Componentes

| Componente | Implementación |
|---|---|
| Frontend | `utp_nomenclaturas/frontend/index.html` — HTML/CSS/JS, sin build, sin dependencias de terceros (salvo `xlsx.full.min.js` vía CDN para exportar Excel en cliente) |
| API | Módulo Drupal `utp_nomenclaturas/`, rutas REST bajo `/api/utp-nomenclaturas/v1/*` — único punto de acceso a la base de datos |
| Persistencia | MySQL, vía Entity API de Drupal |

## Flujo

```
index.html ──fetch()──▶ API (Drupal) ──save()──▶ MySQL
           ◀───JSON───              ◀──query───
```

Cada mutación del frontend (crear, editar, eliminar, duplicar) sigue el
mismo camino: función JS → `apiFetch()` → endpoint REST → Controller →
Service (valida + persiste) → respuesta JSON → actualización del estado
local. No hay estado de negocio en `localStorage`; un reload reconstruye
la vista desde la API.

## Tablas

| Tabla | FK | Contenido |
|---|---|---|
| `campaign` | — | Campañas |
| `ad_set` | `campaign_id` | Conjuntos de anuncios |
| `ad` | `ad_set_id` | Anuncios |
| `manual_utm` | — | UTMs manuales |

Cascada de borrado (`campaign` → `ad_set` → `ad`) vía `ON_DELETE_CASCADE`
en la Entity API — no requiere lógica de aplicación.

Diccionario (segmento, campus, facultad…), condicionales D1-D4 y config
de UTM (`default_url`, `meta_mode`) no son tablas: son dos objetos de
Configuration de Drupal (`utp_nomenclaturas.dictionary`,
`utp_nomenclaturas.utm_config`), versionados junto con el módulo y
exportables con `drush cex`. Se editan desde la vista "Configuración" del
frontend, con el mismo ciclo fetch→API→persistencia que el resto.

## Traza: crear una campaña

```
addCampaign()                       index.html
→ apiPost('/campaigns', payload)
→ POST /api/.../v1/campaigns        X-CSRF-Token
→ TreeController::createCampaign()  src/Controller/TreeController.php
→ TreeManager::createCampaign()     src/Service/TreeManager.php
    valida (§3.2/§3.3) → deriva nombre → $campaign->save()
→ 201 + entidad persistida          addCampaign() actualiza el estado local
```

El resto de las acciones (`saveEdit`, `delCamp`, `dupCamp`,
`addListItem`, `saveManual`…) sigue el mismo patrón contra su
Controller/Service correspondiente — tabla completa en
[SDD §8.5](SDD-UTP-Nomenclaturas.md#85-qué-acción-del-frontend-escribe-en-qué-tabla).
Errores de negocio responden 422/409 y no dejan escritura parcial.

## Verificación de persistencia

Crear un registro, recargar sin caché: el estado se reconstruye desde
`GET /campaigns` (MySQL), no desde el navegador.
