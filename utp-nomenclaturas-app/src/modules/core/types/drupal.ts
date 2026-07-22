/**
 * `drupalSettings` inyectado por `AppController::app()` (Fase 4, §9.5 del
 * SDD) al montar la SPA dentro de Drupal. Ausente en dev standalone
 * (Vite + MSW) — `http.ts` lo trata como opcional.
 */
export interface UtpNomenclaturasSettings {
  apiBase: string;
  csrfToken: string;
}

declare global {
  interface Window {
    drupalSettings?: {
      utpNomenclaturas?: UtpNomenclaturasSettings;
    };
  }
}

export {};
