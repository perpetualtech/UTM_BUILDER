import { useMemo } from "react";
import { useCampaigns } from "@/modules/core/hooks/useCampaigns";

/** §4 del SDD: recalculado en cada mutación (TanStack Query invalida al escribir). */
export function useDashboardStats() {
  const { data: campaigns, isLoading } = useCampaigns();

  const stats = useMemo(() => {
    if (!campaigns) {
      return { campanas: 0, conjuntos: 0, anuncios: 0, plataformas: 0 };
    }
    const plataformas = new Set(campaigns.flatMap((c) => (c.meta.medio ? [c.meta.medio] : [])));
    return {
      campanas: campaigns.length,
      conjuntos: campaigns.reduce((total, c) => total + (c.ad_sets_count ?? 0), 0),
      anuncios: campaigns.reduce((total, c) => total + (c.ads_count ?? 0), 0),
      plataformas: plataformas.size,
    };
  }, [campaigns]);

  return { stats, isLoading };
}
