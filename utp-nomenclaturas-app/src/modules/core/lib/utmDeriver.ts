import type { DictBundle } from "@/modules/core/types/api";

/**
 * Puerto 1:1 de UtmDeriver (PHP, utp_nomenclaturas/src/Service/UtmDeriver.php,
 * Fase 3) — §3.4/Anexo D del SDD. A diferencia de nameBuilder.ts (ADR-003:
 * el cliente SÍ previsualiza nombres), acá no hay preview de UTM antes de
 * guardar — este puerto existe únicamente para que el mock de MSW se
 * comporte igual que el Drupal real, no para producción.
 */

export interface DeriveUtmContext {
  medio: string;
  tipo_camp: string;
  campaign_name: string;
  ad_set_name: string;
  ad_name: string;
  ad_url: string | null | undefined;
  meta_mode: "macro" | "hard";
  default_url: string;
}

export interface DerivedUtm {
  plat: string;
  source: string;
  medium: string;
  campaign: string;
  term: string;
  content: string;
  params: string;
  url: string;
  full: string;
  sep: boolean;
  where: string;
  ga4: string;
}

/** Anexo D: `googleSub` — sub-preset de GoogleAds según tipo_camp. */
export function googleSub(tipoCamp: string): string {
  const t = tipoCamp.toLowerCase();
  if (t.includes("pmax") || t.includes("performance")) return "google-pmax";
  if (t.includes("demand")) return "google-demandgen";
  if (t.includes("video") || t.includes("youtube")) return "google-video";
  if (t.includes("display")) return "google-display";
  return "google-search";
}

function presetForCampaign(bundle: DictBundle, medio: string, tipoCamp: string) {
  const presetKey = medio === "GoogleAds" ? googleSub(tipoCamp) : bundle.medio_to_preset[medio];
  return presetKey ? bundle.utm_presets[presetKey] : undefined;
}

/** Anexo D: `joinUrl(base, qs)` — también usado por el builder de UTM manual (§3.4). */
export function joinUrl(base: string, qs: string): string {
  const trimmed = base.trim();
  if (trimmed === "") {
    return qs;
  }
  const sep = trimmed.includes("?") ? "&" : "?";
  return trimmed.replace(/[?&]+$/, "") + sep + qs;
}

export function deriveUtm(bundle: DictBundle, context: DeriveUtmContext): DerivedUtm | null {
  const preset = presetForCampaign(bundle, context.medio, context.tipo_camp);
  if (!preset) {
    return null;
  }

  let campaignValue: string;
  let termValue: string;
  let contentValue: string;

  if (preset.plat === "Meta" || preset.plat === "Tiktok") {
    if (context.meta_mode === "hard") {
      campaignValue = context.campaign_name;
      termValue = context.ad_set_name;
      contentValue = context.ad_name;
    } else {
      campaignValue = preset.campaign;
      termValue = preset.term;
      contentValue = preset.content;
    }
  } else if (preset.plat === "LinkedIn") {
    campaignValue = context.campaign_name;
    termValue = context.ad_set_name;
    contentValue = context.ad_name;
  } else {
    // Google / DV360: campaign siempre hard, term/content del preset.
    campaignValue = context.campaign_name;
    termValue = preset.term;
    contentValue = preset.content;
  }

  const parts: Array<[string, string]> = [
    ["utm_source", preset.source],
    ["utm_medium", preset.medium],
    ["utm_campaign", campaignValue],
  ];
  if (termValue) parts.push(["utm_term", termValue]);
  if (contentValue) parts.push(["utm_content", contentValue]);

  const params = parts.map(([key, value]) => `${key}=${value}`).join("&");
  const url = context.ad_url || context.default_url || "";
  const paste = bundle.plat_paste[preset.plat] ?? { sep: true, where: "" };

  return {
    plat: preset.plat,
    source: preset.source,
    medium: preset.medium,
    campaign: campaignValue,
    term: termValue,
    content: contentValue,
    params,
    url,
    full: joinUrl(url, params),
    sep: paste.sep,
    where: paste.where,
    ga4: preset.ga4,
  };
}
