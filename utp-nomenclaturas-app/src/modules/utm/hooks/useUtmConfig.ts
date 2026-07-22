import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { UtmConfig } from "@/modules/core/types/api";

/** GET/PATCH /utms/config (§3.4 del SDD) — default_url + meta_mode (macro|hard). */
export function useUtmConfig() {
  return useQuery({
    queryKey: ["utm-config"],
    queryFn: () => http.get<UtmConfig>("/utms/config"),
  });
}

export function useUpdateUtmConfig() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: UtmConfig) => http.patch<UtmConfig>("/utms/config", payload),
    onSuccess: (config) => {
      queryClient.setQueryData(["utm-config"], config);
      // meta_mode afecta directamente los valores de campaign/term/content derivados.
      queryClient.invalidateQueries({ queryKey: ["utms-paid"] });
    },
  });
}
