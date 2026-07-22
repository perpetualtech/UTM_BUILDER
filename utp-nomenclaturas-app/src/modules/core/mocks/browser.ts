import { setupWorker } from "msw/browser";
import { handlers } from "@/modules/core/mocks/handlers";

/** Solo se arranca en dev (main.tsx la invoca detrás de import.meta.env.DEV). */
export const worker = setupWorker(...handlers);
