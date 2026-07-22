import { useQuery } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { PaidUtmRow } from "@/modules/core/types/api";

/**
 * GET /utms/paid (§3.4/§9.4 del SDD) — filas ya derivadas y aplanadas. El
 * filtrado por medio/pilar/búsqueda se hace en el cliente (mismo criterio
 * que `renderPaid()` en el HTML de referencia), no hay parámetros de
 * filtro server-side.
 */
export function usePaidUtms() {
  return useQuery({
    queryKey: ["utms-paid"],
    queryFn: () => http.get<PaidUtmRow[]>("/utms/paid"),
  });
}
