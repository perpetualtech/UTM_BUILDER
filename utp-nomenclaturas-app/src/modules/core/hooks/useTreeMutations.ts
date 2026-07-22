import { useMutation, useQueryClient } from "@tanstack/react-query";
import { http } from "@/modules/core/lib/http";
import type { Ad, AdMeta, AdSet, AdSetMeta, Campaign, CampaignMeta } from "@/modules/core/types/api";

/**
 * Mutaciones del árbol Campaña▸Conjunto▸Anuncio (§7.2 del SDD). Todas
 * invalidan las queries de lista/árbol al completar, para que la UI
 * refleje el estado que acaba de confirmar el servidor (autoritativo,
 * ADR-003).
 */

function useInvalidateTree() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["campaigns"] });
    queryClient.invalidateQueries({ queryKey: ["campaign-tree"] });
  };
}

export function useCreateCampaign() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { pillar_code: string; meta: Partial<CampaignMeta> }) =>
      http.post<Campaign>("/campaigns", payload),
    onSuccess: invalidate,
  });
}

export function useUpdateCampaign(uuid: string) {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { pillar_code?: string; meta?: Partial<CampaignMeta>; name?: string }) =>
      http.patch<Campaign>(`/campaigns/${uuid}`, payload),
    onSuccess: invalidate,
  });
}

export function useDeleteCampaign() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.delete(`/campaigns/${uuid}`),
    onSuccess: invalidate,
  });
}

export function useDuplicateCampaign() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.post<Campaign>(`/campaigns/${uuid}/duplicate`),
    onSuccess: invalidate,
  });
}

export function useCreateAdSet(campaignUuid: string) {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { meta: Partial<AdSetMeta> }) =>
      http.post<AdSet>(`/campaigns/${campaignUuid}/ad-sets`, payload),
    onSuccess: invalidate,
  });
}

export function useUpdateAdSet(uuid: string) {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { meta?: Partial<AdSetMeta>; name?: string }) =>
      http.patch<AdSet>(`/ad-sets/${uuid}`, payload),
    onSuccess: invalidate,
  });
}

export function useDeleteAdSet() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.delete(`/ad-sets/${uuid}`),
    onSuccess: invalidate,
  });
}

export function useDuplicateAdSet() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.post<AdSet>(`/ad-sets/${uuid}/duplicate`),
    onSuccess: invalidate,
  });
}

export function useCreateAd(adSetUuid: string) {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { meta: Partial<AdMeta>; url?: string }) =>
      http.post<Ad>(`/ad-sets/${adSetUuid}/ads`, payload),
    onSuccess: invalidate,
  });
}

export function useUpdateAd(uuid: string) {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (payload: { meta?: Partial<AdMeta>; url?: string; name?: string }) =>
      http.patch<Ad>(`/ads/${uuid}`, payload),
    onSuccess: invalidate,
  });
}

export function useDeleteAd() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.delete(`/ads/${uuid}`),
    onSuccess: invalidate,
  });
}

export function useDuplicateAd() {
  const invalidate = useInvalidateTree();
  return useMutation({
    mutationFn: (uuid: string) => http.post<Ad>(`/ads/${uuid}/duplicate`),
    onSuccess: invalidate,
  });
}
