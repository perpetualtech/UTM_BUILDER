import { useQuery } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { DictBundle } from "@/modules/core/types/api";

/** GET /config (§7.1 del SDD) — un solo fetch, cacheado por TanStack Query. */
export function useDictBundle() {
  return useQuery({
    queryKey: ["dict-bundle"],
    queryFn: () => http.get<DictBundle>("/config"),
    staleTime: Infinity, // el diccionario no cambia durante una sesión (Fase 4 lo invalidará al editar)
  });
}
