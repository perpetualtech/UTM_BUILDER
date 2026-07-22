import { useQuery } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { Campaign, CampaignTree } from "@/modules/core/types/api";

export interface CampaignFilters {
  pillar?: string;
  medio?: string;
  q?: string;
}

function toQueryString(filters: CampaignFilters): string {
  const params = new URLSearchParams();
  if (filters.pillar) params.set("pillar", filters.pillar);
  if (filters.medio) params.set("medio", filters.medio);
  if (filters.q) params.set("q", filters.q);
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

/** GET /campaigns (§7.2 del SDD) — lista filtrable, usada por Dashboard y Repository. */
export function useCampaigns(filters: CampaignFilters = {}) {
  return useQuery({
    queryKey: ["campaigns", filters],
    queryFn: () => http.get<Campaign[]>(`/campaigns${toQueryString(filters)}`),
  });
}

/** GET /campaigns/{uuid}?include=ad_sets.ads (§7.2 del SDD) — árbol completo. */
export function useCampaignTree(uuid: string | undefined) {
  return useQuery({
    queryKey: ["campaign-tree", uuid],
    queryFn: () => http.get<CampaignTree>(`/campaigns/${uuid}?include=ad_sets.ads`),
    enabled: Boolean(uuid),
  });
}
