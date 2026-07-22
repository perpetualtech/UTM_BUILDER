/**
 * Puerto 1:1 de NameBuilder (PHP, utp_nomenclaturas/src/Service/NameBuilder.php,
 * Fase 1) — §3.1 del SDD. Usado para preview instantáneo en el cliente
 * (ADR-003: el servidor sigue siendo autoritativo al escribir) y por los
 * mocks de MSW para que el comportamiento sea el mismo que tendrá el
 * Drupal real.
 *
 * A diferencia del backend PHP (que necesita un fallback manual porque la
 * extensión `intl` no está garantizada), el navegador soporta
 * `String.prototype.normalize('NFD')` de forma nativa — sin fallback.
 */

export interface CampaignNameFields {
  segmento?: string;
  etapa?: string;
  campus?: string;
  medio?: string;
  obj_camp?: string;
  obj_plat?: string;
  tipo_camp?: string;
  pillar_code?: string;
}

export interface AdSetNameFields {
  edad?: string;
  ubicacion?: string;
  facultad?: string;
  senal?: string;
  detalle?: string;
}

export interface AdNameFields {
  formato?: string;
  concepto?: string;
  motivo?: string;
  mensaje?: string;
  carrera?: string;
  fecha?: string;
}

/** slug(v) — §3.1: trim, NFD + strip de diacríticos, espacios → "-". */
export function slug(value: string | null | undefined): string {
  const trimmed = (value ?? "").trim();
  if (trimmed === "") {
    return "";
  }
  const withoutDiacritics = trimmed.normalize("NFD").replace(/\p{Mn}/gu, "");
  return withoutDiacritics.replace(/\s+/g, "-");
}

function slugOrderedFields(
  fields: CampaignNameFields | AdSetNameFields | AdNameFields,
  order: string[],
): string[] {
  const record = fields as Record<string, string | undefined>;
  return order.flatMap((field) => {
    const segment = slug(record[field]);
    return segment === "" ? [] : [segment];
  });
}

/**
 * Nombre derivado de Campaign: 7 campos snapshot + pillar_code al final
 * (§3.1/§2.2 del SDD).
 */
export function campaignName(fields: CampaignNameFields): string {
  if (!fields.pillar_code) {
    throw new Error("campaignName() requiere pillar_code.");
  }
  const order = ["segmento", "etapa", "campus", "medio", "obj_camp", "obj_plat", "tipo_camp"];
  const segments = slugOrderedFields(fields, order);
  segments.push(slug(fields.pillar_code));
  return segments.join("_");
}

/** Nombre derivado de AdSet — §3.1: edad, ubicacion, facultad, senal, detalle. */
export function adSetName(fields: AdSetNameFields): string {
  const order = ["edad", "ubicacion", "facultad", "senal", "detalle"];
  return slugOrderedFields(fields, order).join("_");
}

/** Nombre derivado de Ad — §3.1: formato, concepto, motivo, mensaje, carrera, fecha. */
export function adName(fields: AdNameFields): string {
  const order = ["formato", "concepto", "motivo", "mensaje", "carrera", "fecha"];
  return slugOrderedFields(fields, order).join("_");
}
