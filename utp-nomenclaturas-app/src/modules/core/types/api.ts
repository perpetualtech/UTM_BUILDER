/**
 * Tipos calcados 1:1 de las respuestas JSON del backend (§7 del SDD,
 * TreeController/UtmConfigController de Fase 1 — utp_nomenclaturas/src/).
 * Si el shape del backend cambia, este archivo cambia con él.
 */

export interface DictBundle {
  lists: {
    segmento: string[];
    etapa: string[];
    campus: string[];
    edad: string[];
    ubicacion: string[];
    facultad: string[];
    senal: string[];
    detalle: string[];
    formato: string[];
    nombre: string[];
    motivo: string[];
    mensaje: string[];
    carrera: string[];
    fecha: string[];
  };
  etapa_conditionals: {
    medio: Record<string, string[]>;
    objCamp: Record<string, string[]>;
    objPlat: Record<string, string[]>;
    tipoCamp: Record<string, string[]>;
  };
  segmento_pilar: Record<string, string[]>;
  facultad_nombre: Record<string, string>;
  campus_facultad: Record<string, string[]>;
  ubicacion_grupo: Record<string, string[]>;
  sedes_especificas: string[];
  sede_grupo: Record<string, string>;
  platforms: string[];
  utm_presets: Record<string, {
    plat: string;
    source: string;
    medium: string;
    campaign: string;
    term: string;
    content: string;
    ga4: string;
  }>;
  medio_to_preset: Record<string, string>;
  plat_paste: Record<string, { sep: boolean; where: string }>;
  ux_sources: string[];
  ux_mediums: string[];
  reserved_src: string[];
}

export interface CampaignMeta {
  segmento: string;
  etapa: string;
  campus: string;
  medio: string;
  obj_camp: string;
  obj_plat: string;
  tipo_camp: string;
}

export interface Campaign {
  id: number;
  uuid: string;
  name: string;
  pillar_code: string;
  meta: CampaignMeta;
  ad_sets_count?: number;
  ads_count?: number;
}

export interface AdSetMeta {
  edad: string;
  ubicacion: string;
  facultad: string;
  senal: string;
  detalle: string;
}

export interface AdSet {
  id: number;
  uuid: string;
  name: string;
  meta: AdSetMeta;
  ads?: Ad[];
}

export interface AdMeta {
  formato: string;
  concepto: string;
  motivo: string;
  mensaje: string;
  carrera: string;
  fecha: string;
}

export interface Ad {
  id: number;
  uuid: string;
  name: string;
  url: string | null;
  meta: AdMeta;
}

export interface CampaignTree extends Campaign {
  ad_sets?: AdSet[];
}

/** Forma exacta de los errores del backend (§7 del SDD). */
export interface ApiErrorBody {
  error: string;
  code: string;
  details: Record<string, unknown>;
}

export class ApiError extends Error {
  readonly status: number;
  readonly body: ApiErrorBody;

  constructor(status: number, body: ApiErrorBody) {
    super(body.error);
    this.name = "ApiError";
    this.status = status;
    this.body = body;
  }
}
