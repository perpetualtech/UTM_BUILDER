import { create } from "zustand";

/**
 * Estado de UI del drill-down del Builder (§8.2 del SDD: PillarTabs →
 * CampaignForm → AdSetForm → AdForm). No es estado de servidor — eso lo
 * maneja TanStack Query.
 */
interface BuilderState {
  activePillar: string | null;
  activeCampaignUuid: string | null;
  activeAdSetUuid: string | null;
  setActivePillar: (pillar: string) => void;
  focusCampaign: (uuid: string) => void;
  focusAdSet: (uuid: string) => void;
  reset: () => void;
}

export const useBuilderStore = create<BuilderState>((set) => ({
  activePillar: null,
  activeCampaignUuid: null,
  activeAdSetUuid: null,
  setActivePillar: (pillar) => set({ activePillar: pillar, activeCampaignUuid: null, activeAdSetUuid: null }),
  focusCampaign: (uuid) => set({ activeCampaignUuid: uuid, activeAdSetUuid: null }),
  focusAdSet: (uuid) => set({ activeAdSetUuid: uuid }),
  reset: () => set({ activePillar: null, activeCampaignUuid: null, activeAdSetUuid: null }),
}));
