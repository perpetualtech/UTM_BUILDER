import { describe, expect, it } from "vitest";
import { adName, adSetName, campaignName, slug } from "@/modules/core/lib/nameBuilder";

/**
 * Mismos fixtures que utp_nomenclaturas/tests/src/Unit/NameBuilderTest.php
 * (Fase 1, PHP) — el puerto JS debe producir resultados idénticos, ya que
 * ambos implementan §3.1 del SDD.
 */
describe("slug()", () => {
  it("trims", () => {
    expect(slug("  hola  ")).toBe("hola");
  });

  it("strips diacritics", () => {
    expect(slug("Educación Superior")).toBe("Educacion-Superior");
    expect(slug("niño")).toBe("nino");
  });

  it("replaces spaces with hyphens", () => {
    expect(slug("uno   dos  tres")).toBe("uno-dos-tres");
  });

  it("does not force lowercase", () => {
    expect(slug("Lima Centro")).toBe("Lima-Centro");
  });

  it("returns empty for empty/null input", () => {
    expect(slug("")).toBe("");
    expect(slug("   ")).toBe("");
    expect(slug(null)).toBe("");
    expect(slug(undefined)).toBe("");
  });
});

describe("campaignName()", () => {
  it("builds the name with all fields in order", () => {
    expect(
      campaignName({
        segmento: "jovenes",
        etapa: "upper",
        campus: "lima",
        medio: "Meta",
        obj_camp: "Awareness",
        obj_plat: "Alcance",
        tipo_camp: "Video",
        pillar_code: "calidad",
      }),
    ).toBe("jovenes_upper_lima_Meta_Awareness_Alcance_Video_calidad");
  });

  it("skips empty fields", () => {
    expect(
      campaignName({
        segmento: "jovenes",
        etapa: "",
        campus: "lima",
        medio: undefined,
        obj_camp: "Awareness",
        obj_plat: "",
        tipo_camp: "Video",
        pillar_code: "calidad",
      }),
    ).toBe("jovenes_lima_Awareness_Video_calidad");
  });

  it("requires pillar_code", () => {
    expect(() => campaignName({ segmento: "jovenes", pillar_code: "" })).toThrow();
  });
});

describe("adSetName()", () => {
  it("builds the name with all fields in order", () => {
    expect(
      adSetName({ edad: "j1-j2", ubicacion: "lima-centro", facultad: "ing", senal: "broad", detalle: "gaming-tech" }),
    ).toBe("j1-j2_lima-centro_ing_broad_gaming-tech");
  });

  it("skips empty fields", () => {
    expect(adSetName({ edad: "j1-j2", ubicacion: "", facultad: "ing", senal: undefined, detalle: "custom" })).toBe(
      "j1-j2_ing_custom",
    );
  });

  it("returns empty string when all fields are empty", () => {
    expect(adSetName({})).toBe("");
  });
});

describe("adName()", () => {
  it("builds the name with all fields in order", () => {
    expect(
      adName({
        formato: "video",
        concepto: "empleabilidad",
        motivo: "testimonial",
        mensaje: "estudia-trabaja",
        carrera: "ing-soft",
        fecha: "ene26",
      }),
    ).toBe("video_empleabilidad_testimonial_estudia-trabaja_ing-soft_ene26");
  });

  it("skips empty fields", () => {
    expect(
      adName({ formato: "video", concepto: "", motivo: "testimonial", mensaje: undefined, carrera: "ing-soft", fecha: "" }),
    ).toBe("video_testimonial_ing-soft");
  });

  it("returns empty string when all fields are empty", () => {
    expect(adName({})).toBe("");
  });
});
