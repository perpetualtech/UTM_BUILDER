import { stripDiacritics } from "@/modules/core/lib/nameBuilder";

/**
 * Puerto 1:1 de `clean()` (UTP-Nomenclaturas.html, línea 1459) — §3.4 del
 * SDD: "limpieza opcional" del builder de UTM manual (lowercase + NFD +
 * espacios → "-"). A diferencia de slug() (naming), acá SÍ se fuerza
 * minúsculas.
 */
export function clean(value: string | null | undefined, enabled: boolean): string {
  const trimmed = (value ?? "").trim();
  if (!enabled) {
    return trimmed;
  }
  return stripDiacritics(trimmed.toLowerCase()).replace(/\s+/g, "-");
}
