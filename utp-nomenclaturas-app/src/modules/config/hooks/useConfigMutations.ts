import { useMutation, useQueryClient } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";

/**
 * Config Nivel 1/2/3 (Fase 4, §7.1 del SDD). Todas invalidan `["dict-bundle"]`
 * al completar — el bundle se vuelve a pedir y D1/D2/D3 reflejan el cambio
 * de inmediato en cualquier pantalla que ya esté abierta (AC: "editar la
 * matriz impacta D3 en vivo").
 */
function useInvalidateDictBundle() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["dict-bundle"] });
}

export function useAddListValue() {
  const invalidate = useInvalidateDictBundle();
  return useMutation({
    mutationFn: ({ listKey, value }: { listKey: string; value: string }) =>
      http.post(`/config/lists/${listKey}/values`, { value }),
    onSuccess: invalidate,
  });
}

export function useDeleteListValue() {
  const invalidate = useInvalidateDictBundle();
  return useMutation({
    mutationFn: ({ listKey, value }: { listKey: string; value: string }) =>
      http.delete(`/config/lists/${listKey}/values/${encodeURIComponent(value)}`),
    onSuccess: invalidate,
  });
}

export function useUpdateEtapaOptions() {
  const invalidate = useInvalidateDictBundle();
  return useMutation({
    mutationFn: ({ etapa, field, values }: { etapa: string; field: string; values: string[] }) =>
      http.put(`/config/etapa-options/${etapa}/${field}`, { values }),
    onSuccess: invalidate,
  });
}

export function useUpdateSegmentoPilar() {
  const invalidate = useInvalidateDictBundle();
  return useMutation({
    mutationFn: ({ segmento, pilares }: { segmento: string; pilares: string[] }) =>
      http.put(`/config/segmento-pilar/${segmento}`, { pilares }),
    onSuccess: invalidate,
  });
}

export function useUpdateCampusFacultad() {
  const invalidate = useInvalidateDictBundle();
  return useMutation({
    mutationFn: (matrix: Record<string, string[]>) => http.put("/config/campus-facultad", { matrix }),
    onSuccess: invalidate,
  });
}
