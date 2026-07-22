# utp-nomenclaturas-app

SPA en React 19 + TypeScript (Vite, Tailwind v4, shadcn/ui, TanStack
Query, Zustand) del proyecto **UTP · Constructor de Nomenclaturas**. Ver
el [README de la raíz](../README.md) para arquitectura completa,
instalación del backend y variables de entorno.

## Desarrollo

```bash
npm install
npm run dev
```

Corre standalone contra mocks en memoria (MSW) que reproducen el
contrato completo de la API — no necesita el módulo Drupal levantado.

## Scripts

| Comando | Qué hace |
|---|---|
| `npm run dev` | Servidor de desarrollo con hot reload |
| `npm run build` | Build de producción (incluye `.vite/manifest.json`, que lee `AppController.php` para montar la SPA en Drupal) |
| `npm run lint` | ESLint |
| `npm run test` | Vitest |
| `npm run test:ui` | Vitest con UI |

## Variables de entorno

Ver [`.env.example`](.env.example) — solo aplica en modo standalone
(`npm run dev`/`vite build` fuera de Drupal). Montada dentro de Drupal,
la URL base de la API y el token CSRF llegan por `drupalSettings`, no
por variables de entorno.

## Estructura

Arquitectura modular por feature (`src/modules/<feature>/`): cada módulo
agrupa sus propios `components/`, `hooks/`, `pages/`, `lib/`, etc.
`core/` centraliza lo compartido (design system, cliente HTTP, mocks).
