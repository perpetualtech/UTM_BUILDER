import { describe, expect, it } from "vitest";
import {
  codeExistsInList,
  getFacultadesForUbicacion,
  getOptionsForEtapa,
  getPilaresForSegmento,
  isFacultadValidForUbicacion,
  isValidPilarSegmentoCombination,
} from "@/modules/core/lib/dictionaryRules";
import { seedDictionary } from "@/modules/core/mocks/seedDictionary";

/**
 * Mismos fixtures que utp_nomenclaturas/tests/src/Kernel/DictionaryConditionalTest.php
 * (Fase 1, PHP) — datos reales del Anexo A, no inventados.
 */
describe("D1 — getOptionsForEtapa()", () => {
  it("upper.medio incluye las 5 plataformas", () => {
    expect(getOptionsForEtapa(seedDictionary, "upper", "medio")).toEqual([
      "Meta", "Tiktok", "DV360", "LinkedIn", "GoogleAds",
    ]);
  });

  it("lower.medio excluye DV360", () => {
    const options = getOptionsForEtapa(seedDictionary, "lower", "medio");
    expect(options).toEqual(["Meta", "Tiktok", "GoogleAds", "LinkedIn"]);
    expect(options).not.toContain("DV360");
  });

  it("middle.objCamp", () => {
    expect(getOptionsForEtapa(seedDictionary, "middle", "objCamp")).toEqual([
      "Tráfico RMKT", "Qualifed Traffic", "Conversiones",
    ]);
  });

  it("etapa inexistente devuelve []", () => {
    expect(getOptionsForEtapa(seedDictionary, "etapa-inexistente", "medio")).toEqual([]);
  });

  it("field inválido lanza excepción", () => {
    // @ts-expect-error — probamos deliberadamente un field no condicionado
    expect(() => getOptionsForEtapa(seedDictionary, "upper", "segmento")).toThrow();
  });
});

describe("D2 — segmento → pilar", () => {
  it("jovenes incluye empleabilidad", () => {
    expect(getPilaresForSegmento(seedDictionary, "jovenes")).toContain("empleabilidad");
  });

  it("adultos excluye empleabilidad", () => {
    const pilares = getPilaresForSegmento(seedDictionary, "adultos");
    expect(pilares).toEqual(["calidad", "accesibilidad", "orgullo"]);
    expect(pilares).not.toContain("empleabilidad");
  });

  it("empleabilidad solo es válido para jovenes", () => {
    expect(isValidPilarSegmentoCombination(seedDictionary, "empleabilidad", "adultos")).toBe(false);
    expect(isValidPilarSegmentoCombination(seedDictionary, "empleabilidad", "jovenes")).toBe(true);
  });
});

describe("D3 — ubicacion → facultad", () => {
  it("facultad sin restricción siempre disponible", () => {
    expect(isFacultadValidForUbicacion(seedDictionary, "ing", "trujillo")).toBe(true);
    expect(isFacultadValidForUbicacion(seedDictionary, "ing", "lima-centro")).toBe(true);
  });

  it("facultad restringida válida en sede exacta", () => {
    expect(isFacultadValidForUbicacion(seedDictionary, "med", "lima-centro")).toBe(true);
    expect(isFacultadValidForUbicacion(seedDictionary, "med", "arequipa")).toBe(true);
  });

  it("facultad restringida inválida fuera de sus sedes", () => {
    expect(isFacultadValidForUbicacion(seedDictionary, "med", "lima-norte")).toBe(false);
  });

  it("facultad restringida válida vía grupo con intersección", () => {
    expect(isFacultadValidForUbicacion(seedDictionary, "med", "lideres")).toBe(true);
    expect(isFacultadValidForUbicacion(seedDictionary, "com", "lima")).toBe(true);
  });

  it("facultad restringida inválida vía grupo sin intersección", () => {
    expect(isFacultadValidForUbicacion(seedDictionary, "com", "def-chall")).toBe(false);
    expect(isFacultadValidForUbicacion(seedDictionary, "med", "def-chall")).toBe(false);
  });

  it("getFacultadesForUbicacion excluye restringidas fuera de scope", () => {
    const facultades = getFacultadesForUbicacion(seedDictionary, "def-chall");
    expect(facultades).toContain("ing");
    expect(facultades).not.toContain("com");
    expect(facultades).not.toContain("med");
  });
});

describe("codeExistsInList()", () => {
  it("valida existencia en la lista", () => {
    expect(codeExistsInList(seedDictionary, "segmento", "jovenes")).toBe(true);
    expect(codeExistsInList(seedDictionary, "segmento", "codigo-inexistente")).toBe(false);
  });
});
