import { useMutation, useQueryClient } from "@tanstack/react-query";
import { fetchFile, http, triggerDownload } from "@/modules/core/lib/http";
import type { ImportSummary } from "@/modules/core/types/api";

function toQueryString(params: Record<string, string | string[] | undefined>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (!value) continue;
    if (Array.isArray(value)) {
      value.forEach((v) => search.append(`${key}[]`, v));
    } else {
      search.set(key, value);
    }
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** GET /export/campaigns.xlsx (§3.5 del SDD) — descarga el Excel de nomenclaturas. */
export function useExportCampaignsExcel() {
  return useMutation({
    mutationFn: async (params: { pillar?: string; medio?: string; q?: string; uuids?: string[] }) => {
      const { blob, filename } = await fetchFile(`/export/campaigns.xlsx${toQueryString(params)}`);
      triggerDownload(blob, filename);
    },
  });
}

/** GET /export/utms.xlsx (§3.5 del SDD) — descarga el Excel de UTMs (paid + manual). */
export function useExportUtmsExcel() {
  return useMutation({
    mutationFn: async () => {
      const { blob, filename } = await fetchFile("/export/utms.xlsx");
      triggerDownload(blob, filename);
    },
  });
}

/** GET /export/backup.json (§10 del SDD) — descarga el backup completo del árbol. */
export function useExportBackup() {
  return useMutation({
    mutationFn: async () => {
      const { blob, filename } = await fetchFile("/export/backup.json");
      triggerDownload(blob, filename);
    },
  });
}

/** POST /import (§10 del SDD) — restaura un backup (propio o del HTML legacy). */
export function useImportBackup() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: unknown) => http.post<ImportSummary>("/import", data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["campaigns"] });
      queryClient.invalidateQueries({ queryKey: ["campaign-tree"] });
      queryClient.invalidateQueries({ queryKey: ["utms-paid"] });
    },
  });
}
