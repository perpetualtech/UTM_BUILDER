import { setupServer } from "msw/node";
import { handlers } from "@/modules/core/mocks/handlers";

/** Solo para Vitest — ver src/setupTests.ts. */
export const server = setupServer(...handlers);
