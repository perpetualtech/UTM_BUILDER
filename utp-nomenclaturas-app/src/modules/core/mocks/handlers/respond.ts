import { HttpResponse } from "msw";
import { MockApiError } from "@/modules/core/mocks/store";

/**
 * Envuelve la lógica de un handler MSW: convierte MockApiError en la misma
 * forma {error, code, details} + status code que produce
 * UtpNomenclaturaExceptionSubscriber en el backend real (§7 del SDD).
 */
export function respond<T>(fn: () => T, successStatus = 200) {
  try {
    const data = fn();
    return HttpResponse.json(data ?? null, { status: successStatus });
  } catch (error) {
    if (error instanceof MockApiError) {
      return HttpResponse.json(
        { error: error.message, code: error.code, details: error.details },
        { status: error.status },
      );
    }
    throw error;
  }
}
