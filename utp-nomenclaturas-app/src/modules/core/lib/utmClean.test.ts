import { describe, expect, it } from "vitest";
import { clean } from "@/modules/core/lib/utmClean";

/** Puerto 1:1 de clean() (HTML línea 1459) — §3.4 del SDD. */
describe("clean()", () => {
  it("trim + lowercase + sin diacríticos + espacios a guiones cuando está habilitado", () => {
    expect(clean("  Instagram Ads  ", true)).toBe("instagram-ads");
    expect(clean("Educación Superior", true)).toBe("educacion-superior");
    expect(clean("Niño", true)).toBe("nino");
  });

  it("solo hace trim cuando está deshabilitado (no fuerza minúsculas ni quita acentos)", () => {
    expect(clean("  Educación Superior  ", false)).toBe("Educación Superior");
  });

  it("vacío o nulo produce string vacío", () => {
    expect(clean("", true)).toBe("");
    expect(clean(undefined, true)).toBe("");
    expect(clean(null, true)).toBe("");
  });
});
