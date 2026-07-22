import { describe, expect, it } from "vitest";
import { deriveUtm, googleSub, type DeriveUtmContext } from "@/modules/core/lib/utmDeriver";
import { seedDictionary } from "@/modules/core/mocks/seedDictionary";

/**
 * Puerto TS de UtmDeriver (PHP) — mismos casos que
 * utp_nomenclaturas/tests/src/Unit/UtmDeriverTest.php, contra el mismo
 * seedDictionary (Anexo B) verificado byte-a-byte en Fase 2.
 */

function baseContext(overrides: Partial<DeriveUtmContext> = {}): DeriveUtmContext {
  return {
    medio: "Meta",
    tipo_camp: "",
    campaign_name: "jovenes_upper_lima_meta_awareness_alcance_video_calidad",
    ad_set_name: "j1_lima_ing_broad_gaming-tech",
    ad_name: "video_marca_testimonial_estudia-trabaja_no-carreras_ene26",
    ad_url: "https://utp.edu.pe/landing",
    meta_mode: "macro",
    default_url: "",
    ...overrides,
  };
}

describe("deriveUtm — Meta/Tiktok macro vs hard", () => {
  it("Meta en modo macro usa los tokens de plantilla", () => {
    const row = deriveUtm(seedDictionary, baseContext());
    expect(row?.source).toBe("facebook");
    expect(row?.medium).toBe("cpc");
    expect(row?.campaign).toBe("{{campaign.name}}");
    expect(row?.term).toBe("{{adset.name}}");
    expect(row?.content).toBe("{{ad.name}}");
    expect(row?.ga4).toBe("Paid Social");
    expect(row?.sep).toBe(true);
  });

  it("Meta en modo hard usa los nombres reales", () => {
    const context = baseContext({ meta_mode: "hard" });
    const row = deriveUtm(seedDictionary, context);
    expect(row?.campaign).toBe(context.campaign_name);
    expect(row?.term).toBe(context.ad_set_name);
    expect(row?.content).toBe(context.ad_name);
  });

  it("Tiktok en modo macro usa los tokens de plantilla", () => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "Tiktok" }));
    expect(row?.source).toBe("tiktok");
    expect(row?.campaign).toBe("__CAMPAIGN_NAME__");
    expect(row?.term).toBe("__AID_NAME__");
    expect(row?.content).toBe("__CID_NAME__");
  });
});

describe("deriveUtm — LinkedIn siempre hard", () => {
  it("usa nombres reales sin importar meta_mode", () => {
    const context = baseContext({ medio: "LinkedIn", meta_mode: "macro" });
    const row = deriveUtm(seedDictionary, context);
    expect(row?.campaign).toBe(context.campaign_name);
    expect(row?.term).toBe(context.ad_set_name);
    expect(row?.content).toBe(context.ad_name);
    expect(row?.source).toBe("linkedin");
  });
});

describe("deriveUtm — GoogleAds sub-preset (googleSub)", () => {
  it("search es el sub-preset por defecto", () => {
    expect(googleSub("Search")).toBe("google-search");
    const row = deriveUtm(seedDictionary, baseContext({ medio: "GoogleAds", tipo_camp: "Search" }));
    expect(row?.ga4).toBe("Paid Search");
    expect(row?.term).toBe("{keyword}");
  });

  it.each(["PMAX", "Performance Max"])("%s → google-pmax (sin utm_term)", (tipoCamp) => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "GoogleAds", tipo_camp: tipoCamp }));
    expect(row?.ga4).toBe("Cross-network");
    expect(row?.term).toBe("");
    expect(row?.content).toBe("{resource group}");
    expect(row?.params).not.toContain("utm_term=");
  });

  it("Demand-Gen → google-demandgen", () => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "GoogleAds", tipo_camp: "Demand-Gen" }));
    expect(row?.ga4).toBe("Cross-network / Paid");
    expect(row?.content).toBe("{conjuntodeanuncio}");
  });

  it.each(["Video", "Youtube"])("%s → google-video", (tipoCamp) => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "GoogleAds", tipo_camp: tipoCamp }));
    expect(row?.ga4).toBe("Video");
    expect(row?.medium).toBe("cpv");
  });

  it("Display → google-display", () => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "GoogleAds", tipo_camp: "Display" }));
    expect(row?.ga4).toBe("Display");
    expect(row?.term).toBe("{placement}");
  });

  it("utm_campaign siempre es hard, sin importar meta_mode", () => {
    const context = baseContext({ medio: "GoogleAds", tipo_camp: "Search", meta_mode: "macro" });
    const row = deriveUtm(seedDictionary, context);
    expect(row?.campaign).toBe(context.campaign_name);
  });
});

describe("deriveUtm — DV360", () => {
  it("usa campaign hard y sep=false (URL+params juntos)", () => {
    const row = deriveUtm(seedDictionary, baseContext({ medio: "DV360", meta_mode: "macro" }));
    expect(row?.source).toBe("dv360");
    expect(row?.term).toBe("${CREATIVE_ID}");
    expect(row?.content).toBe("${LINE_ITEM_ID}");
    expect(row?.sep).toBe(false);
  });
});

describe("deriveUtm — URL / joinUrl", () => {
  it("usa default_url cuando el anuncio no tiene URL propia", () => {
    const row = deriveUtm(seedDictionary, baseContext({ ad_url: "", default_url: "https://utp.edu.pe/default" }));
    expect(row?.url).toBe("https://utp.edu.pe/default");
    expect(row?.full.startsWith("https://utp.edu.pe/default?")).toBe(true);
  });

  it("agrega & cuando la URL ya tiene query string", () => {
    const row = deriveUtm(seedDictionary, baseContext({ ad_url: "https://utp.edu.pe/landing?ref=ig" }));
    expect(row?.full).toContain("landing?ref=ig&utm_source=");
  });
});

describe("deriveUtm — medio sin preset", () => {
  it("devuelve null", () => {
    expect(deriveUtm(seedDictionary, baseContext({ medio: "Email" }))).toBeNull();
  });
});
