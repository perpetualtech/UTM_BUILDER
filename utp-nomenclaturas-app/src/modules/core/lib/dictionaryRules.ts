import type { DictBundle } from "@/modules/core/types/api";

/**
 * Puerto 1:1 de las resoluciones D1-D3 de DictionaryProvider (PHP,
 * utp_nomenclaturas/src/Service/DictionaryProvider.php, Fase 1) — §3.2 del
 * SDD. Usado por useDependentOptions (§8.4, para poblar/deshabilitar
 * selects) y por los mocks de MSW. D4 (matriz editable Campus×Facultad) es
 * Fase 4 — acá solo se LEE `campus_facultad`.
 */

const ETAPA_CONDITIONED_FIELDS = ["medio", "objCamp", "objPlat", "tipoCamp"] as const;
export type EtapaConditionedField = (typeof ETAPA_CONDITIONED_FIELDS)[number];

/** D1 (§3.2): opciones válidas de un campo condicionado por etapa. */
export function getOptionsForEtapa(
  bundle: DictBundle,
  etapa: string,
  field: EtapaConditionedField,
): string[] {
  if (!ETAPA_CONDITIONED_FIELDS.includes(field)) {
    throw new Error(
      `'${field}' no es un campo condicionado por etapa. Válidos: ${ETAPA_CONDITIONED_FIELDS.join(", ")}`,
    );
  }
  return bundle.etapa_conditionals[field]?.[etapa] ?? [];
}

/** D2 (§3.2): pilares válidos para un segmento. */
export function getPilaresForSegmento(bundle: DictBundle, segmento: string): string[] {
  return bundle.segmento_pilar[segmento] ?? [];
}

/**
 * D2 (§3.2): valida la combinación pilar+segmento. No hace falta un caso
 * especial para "empleabilidad" — el dato sembrado ya lo excluye de
 * segmento_pilar.adultos.
 */
export function isValidPilarSegmentoCombination(
  bundle: DictBundle,
  pilar: string,
  segmento: string,
): boolean {
  return getPilaresForSegmento(bundle, segmento).includes(pilar);
}

function isFacultadDisponible(
  facultad: string,
  ubicacion: string,
  campusFacultad: Record<string, string[]>,
  ubicacionGrupo: Record<string, string[]>,
): boolean {
  const sedesPermitidas = campusFacultad[facultad];
  if (!sedesPermitidas) {
    return true; // sin restricción → disponible en cualquier ubicación
  }
  const sedesPermitidasSet = new Set(sedesPermitidas);
  if (sedesPermitidasSet.has(ubicacion)) {
    return true;
  }
  const miembrosGrupo = ubicacionGrupo[ubicacion];
  if (miembrosGrupo) {
    return miembrosGrupo.some((sede) => sedesPermitidasSet.has(sede));
  }
  return false;
}

/** D3 (§3.2): facultades disponibles para una ubicación. */
export function getFacultadesForUbicacion(bundle: DictBundle, ubicacion: string): string[] {
  return bundle.lists.facultad.filter((facultad) =>
    isFacultadDisponible(facultad, ubicacion, bundle.campus_facultad, bundle.ubicacion_grupo),
  );
}

/** D3 (§3.2): valida si una facultad concreta está disponible en una ubicación. */
export function isFacultadValidForUbicacion(
  bundle: DictBundle,
  facultad: string,
  ubicacion: string,
): boolean {
  return isFacultadDisponible(facultad, ubicacion, bundle.campus_facultad, bundle.ubicacion_grupo);
}

/** Verifica si un código existe en una lista plana activa del diccionario. */
export function codeExistsInList(
  bundle: DictBundle,
  listKey: keyof DictBundle["lists"],
  value: string,
): boolean {
  return bundle.lists[listKey].includes(value);
}
