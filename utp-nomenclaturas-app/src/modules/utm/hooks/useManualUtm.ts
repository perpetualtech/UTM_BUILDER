import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { ManualUtm, ManualUtmInput } from "@/modules/core/types/api";

/** GET /utms/manual (§3.4/§2.1 del SDD) — tráfico sin pauta (influencers, etc). */
export function useManualUtms() {
  return useQuery({
    queryKey: ["manual-utms"],
    queryFn: () => http.get<ManualUtm[]>("/utms/manual"),
  });
}

export function useCreateManualUtm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: ManualUtmInput) => http.post<ManualUtm>("/utms/manual", payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["manual-utms"] }),
  });
}

export function useDeleteManualUtm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (uuid: string) => http.delete(`/utms/manual/${uuid}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["manual-utms"] }),
  });
}
