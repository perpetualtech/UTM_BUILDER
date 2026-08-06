# Cómo funciona — Frontend, API y Base de Datos

Guía corta, sin detalle técnico de más: qué es el frontend, qué es la API,
cómo se conectan entre sí, y cuáles son las tablas reales donde se guarda
todo. Para el detalle completo (reglas de negocio, condicionales, plan de
fases) está `SDD-UTP-Nomenclaturas.md`; esto es solo lo esencial.

## Las 3 piezas

1. **Frontend** — un solo archivo:
   [`utp_nomenclaturas/js/app/index.html`](utp_nomenclaturas/js/app/index.html).
   HTML + CSS + JavaScript, sin frameworks, sin instalar nada, sin
   compilar. Es la pantalla que usa la persona que carga campañas.

2. **API** — el módulo Drupal (`utp_nomenclaturas/`) expone un conjunto
   de URLs bajo `/api/utp-nomenclaturas/v1/...`. Es la única puerta de
   entrada a la base de datos: el frontend nunca toca MySQL directo,
   siempre pasa por acá.

3. **Base de datos** — MySQL, la maneja Drupal. 4 tablas reales (abajo).

## Cómo se conectan

```
 index.html  ──fetch()──▶  API (Drupal/PHP)  ──guarda──▶  MySQL
 (frontend)  ◀──JSON────   (backend)          ◀─lee──────
```

Cada botón del frontend (Agregar, Editar, Eliminar, Duplicar, Guardar…)
llama a una función de JavaScript, que llama a una URL de la API, que
ejecuta un `INSERT`/`UPDATE`/`DELETE` real en MySQL, y devuelve el
resultado para actualizar la pantalla. No hay ningún dato de campañas,
conjuntos, anuncios o UTMs guardado en el navegador — si se recarga la
página, todo se vuelve a pedir a la base de datos.

## Las 4 tablas reales

| Tabla MySQL | Qué guarda | Se llena cuando... |
|---|---|---|
| `campaign` | Cada campaña creada | El usuario hace clic en "Agregar campaña" |
| `ad_set` | Cada conjunto de anuncios (pertenece a una campaña) | "Agregar conjunto" |
| `ad` | Cada anuncio (pertenece a un conjunto) | "Agregar anuncio" |
| `manual_utm` | Cada UTM manual (influencers, orgánico) | "Guardar UTM" en la pestaña Manual |

`ad_set` guarda a qué `campaign` pertenece, y `ad` guarda a qué `ad_set`
pertenece — si se borra una campaña, sus conjuntos y anuncios se borran
solos (lo hace la base de datos, no hay que borrarlos a mano).

Dos cosas más chicas, que **no son tablas** sino un archivo de
configuración interno de Drupal (porque casi no cambian y conviene
llevarlas versionadas junto con el módulo, no como filas sueltas):
las **listas del diccionario** (segmento, campus, facultad, etc., y sus
reglas de qué combina con qué) y la **configuración de UTM** (modo
macro/hard). Se editan desde la pantalla de "Configuración" del
frontend, igual que cualquier otro dato.

## Ejemplo completo: crear una campaña

1. El usuario llena el formulario y hace clic en **"Agregar campaña"**.
2. El frontend llama a `POST /api/utp-nomenclaturas/v1/campaigns` con los
   datos del formulario.
3. Drupal recibe esa llamada, valida los datos (ej. que el campus elegido
   exista), arma el nombre de la campaña, y hace `INSERT` en la tabla
   `campaign`.
4. Drupal responde con la campaña ya guardada (con su ID real de base de
   datos).
5. El frontend muestra esa campaña en pantalla.

Todas las demás acciones (editar, eliminar, duplicar, conjuntos,
anuncios, UTMs, configuración) siguen el mismo camino, cambiando solo la
URL y la tabla de destino — el detalle línea por línea de cada una está
en [`README.md`](README.md#cómo-se-conectan-frontend-backend-y-base-de-datos)
y en la tabla completa del
[`SDD §8.5`](SDD-UTP-Nomenclaturas.md#85-qué-acción-del-frontend-escribe-en-qué-tabla).

## Cómo comprobar que es real (no una demo)

Crear una campaña y recargar la página. Si siguiera ahí por un truco del
navegador, desaparecería en modo incógnito o en otra computadora — acá no
desaparece, porque viene de MySQL, no del navegador.
