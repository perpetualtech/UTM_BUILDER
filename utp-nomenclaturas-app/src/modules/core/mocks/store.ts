import { adName, adSetName, campaignName } from "@/modules/core/lib/nameBuilder";
import {
  codeExistsInList,
  getOptionsForEtapa,
  isFacultadValidForUbicacion,
  isValidPilarSegmentoCombination,
} from "@/modules/core/lib/dictionaryRules";
import { seedDictionary } from "@/modules/core/mocks/seedDictionary";
import type { Ad, AdMeta, AdSet, AdSetMeta, Campaign, CampaignMeta } from "@/modules/core/types/api";

/**
 * Store en memoria para los mocks de MSW — reproduce el comportamiento de
 * TreeManager (PHP, Fase 1): validación D1-D3 + existencia en diccionario
 * + unicidad + derivación de nombre, para que crear/editar/eliminar en la
 * UI se comporte igual que contra el Drupal real (§7 del SDD).
 */

type StoredAd = Ad;
interface StoredAdSet extends AdSet {
  campaign_id: number;
  ads: StoredAd[];
}
interface StoredCampaign extends Campaign {
  ad_sets: StoredAdSet[];
}

export class MockApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details: Record<string, unknown>;

  constructor(status: number, code: string, message: string, details: Record<string, unknown> = {}) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

let nextId = 1;
let campaigns: StoredCampaign[] = [];

export function resetStore(): void {
  campaigns = [];
  nextId = 1;
}

function fail(status: number, code: string, message: string, details: Record<string, unknown> = {}): never {
  throw new MockApiError(status, code, message, details);
}

function validationFailed(field: string, reason: string): never {
  fail(422, "VALIDATION_FAILED", "Código inexistente en el diccionario.", {
    violations: [{ field, reason }],
  });
}

function conditionalViolation(conditionalId: string, message: string, field: string, reason: string): never {
  fail(422, "VALIDATION_FAILED", message, {
    violations: [{ field, reason }],
    conditional_id: conditionalId,
  });
}

const D1_FIELD_MAP = {
  medio: "medio",
  obj_camp: "objCamp",
  obj_plat: "objPlat",
  tipo_camp: "tipoCamp",
} as const;

function validateCampaignFields(meta: CampaignMeta, pillarCode: string): void {
  if (!meta.segmento) {
    conditionalViolation("D2", "segmento es requerido para validar el pilar.", "segmento", "Requerido");
  }
  if (!codeExistsInList(seedDictionary, "segmento", meta.segmento)) {
    validationFailed("segmento", `Valor '${meta.segmento}' no existe en la lista 'segmento'.`);
  }
  if (!isValidPilarSegmentoCombination(seedDictionary, pillarCode, meta.segmento)) {
    conditionalViolation(
      "D2",
      `El pilar '${pillarCode}' no es válido para segmento='${meta.segmento}'.`,
      "pillar_code",
      `No compatible con segmento='${meta.segmento}'.`,
    );
  }

  for (const field of ["etapa", "campus"] as const) {
    if (meta[field] && !codeExistsInList(seedDictionary, field, meta[field])) {
      validationFailed(field, `Valor '${meta[field]}' no existe en la lista '${field}'.`);
    }
  }

  for (const [entityField, configField] of Object.entries(D1_FIELD_MAP) as Array<
    [keyof typeof D1_FIELD_MAP, "medio" | "objCamp" | "objPlat" | "tipoCamp"]
  >) {
    const value = meta[entityField];
    if (!value) continue;
    if (!meta.etapa) {
      conditionalViolation("D1", `'${entityField}' requiere una etapa seleccionada.`, entityField, "etapa no seleccionada");
    }
    const allowed = getOptionsForEtapa(seedDictionary, meta.etapa, configField);
    if (!allowed.includes(value)) {
      conditionalViolation(
        "D1",
        `'${entityField}'='${value}' no es válido para etapa='${meta.etapa}'.`,
        entityField,
        `Valor '${value}' no permitido para etapa='${meta.etapa}'.`,
      );
    }
  }
}

function validateAdSetFields(meta: AdSetMeta): void {
  for (const field of ["edad", "ubicacion", "senal"] as const) {
    if (meta[field] && !codeExistsInList(seedDictionary, field, meta[field])) {
      validationFailed(field, `Valor '${meta[field]}' no existe en la lista '${field}'.`);
    }
  }
  if (meta.facultad) {
    if (!codeExistsInList(seedDictionary, "facultad", meta.facultad)) {
      validationFailed("facultad", `Valor '${meta.facultad}' no existe en la lista 'facultad'.`);
    }
    if (meta.ubicacion && !isFacultadValidForUbicacion(seedDictionary, meta.facultad, meta.ubicacion)) {
      conditionalViolation(
        "D3",
        `facultad '${meta.facultad}' no está disponible para ubicacion='${meta.ubicacion}'.`,
        "facultad",
        `No disponible para ubicacion='${meta.ubicacion}'.`,
      );
    }
  }
}

function validateAdFields(meta: AdMeta): void {
  for (const field of ["formato", "motivo", "carrera", "fecha"] as const) {
    if (meta[field] && !codeExistsInList(seedDictionary, field, meta[field])) {
      validationFailed(field, `Valor '${meta[field]}' no existe en la lista '${field}'.`);
    }
  }
  if (meta.concepto && !codeExistsInList(seedDictionary, "nombre", meta.concepto)) {
    validationFailed("concepto", `Valor '${meta.concepto}' no existe en la lista 'nombre'.`);
  }
}

function resolveAvailableName(baseName: string, exists: (candidate: string) => boolean): string {
  if (!exists(baseName)) return baseName;
  let attempt = 2;
  let candidate: string;
  do {
    candidate = attempt === 2 ? `${baseName}-copia` : `${baseName}-copia-${attempt}`;
    attempt += 1;
  } while (exists(candidate));
  return candidate;
}

// ---- Campaign -----------------------------------------------------------

export function listCampaigns(filters: { pillar?: string; medio?: string; q?: string }): Campaign[] {
  return campaigns
    .filter((c) => !filters.pillar || c.pillar_code === filters.pillar)
    .filter((c) => !filters.medio || c.meta.medio === filters.medio)
    .filter((c) => !filters.q || c.name.toLowerCase().includes(filters.q!.toLowerCase()))
    .map(({ ad_sets, ...c }) => ({
      ...c,
      ad_sets_count: ad_sets.length,
      // §4 del SDD: KPI de "anuncios" = Σ anuncios de todos los conjuntos.
      ads_count: ad_sets.reduce((total, adSet) => total + adSet.ads.length, 0),
    }));
}

export function getCampaignByUuid(uuid: string): StoredCampaign | undefined {
  return campaigns.find((c) => c.uuid === uuid);
}

export function createCampaign(payload: { pillar_code?: string; meta?: Partial<CampaignMeta> }): StoredCampaign {
  const pillarCode = (payload.pillar_code ?? "").trim();
  if (!pillarCode) {
    fail(422, "VALIDATION_FAILED", "pillar_code es requerido.", {
      violations: [{ field: "pillar_code", reason: "Requerido" }],
    });
  }

  const meta: CampaignMeta = {
    segmento: payload.meta?.segmento ?? "",
    etapa: payload.meta?.etapa ?? "",
    campus: payload.meta?.campus ?? "",
    medio: payload.meta?.medio ?? "",
    obj_camp: payload.meta?.obj_camp ?? "",
    obj_plat: payload.meta?.obj_plat ?? "",
    tipo_camp: payload.meta?.tipo_camp ?? "",
  };
  validateCampaignFields(meta, pillarCode);

  const name = campaignName({ ...meta, pillar_code: pillarCode });
  if (campaigns.some((c) => c.pillar_code === pillarCode && c.name === name)) {
    fail(409, "DUPLICATE_NAME", `campaign '${name}' ya existe dentro de pilar '${pillarCode}'.`, {
      entity_type: "campaign",
      name,
      uniqueness_scope: `pilar '${pillarCode}'`,
    });
  }

  const campaign: StoredCampaign = {
    id: nextId++,
    uuid: crypto.randomUUID(),
    name,
    pillar_code: pillarCode,
    meta,
    ad_sets: [],
  };
  campaigns.push(campaign);
  return campaign;
}

export function updateCampaign(
  uuid: string,
  payload: { pillar_code?: string; meta?: Partial<CampaignMeta>; name?: string },
): StoredCampaign {
  const campaign = getCampaignByUuid(uuid);
  if (!campaign) fail(404, "NOT_FOUND", "No encontrado.");

  const pillarCode = payload.pillar_code !== undefined ? payload.pillar_code.trim() : campaign.pillar_code;

  // Override manual de nombre (edición inline en Repository) — no re-deriva
  // desde meta, igual que saveEdit() en el HTML de referencia.
  if (payload.name && payload.name.trim() !== "") {
    const newName = payload.name.trim();
    if (newName !== campaign.name && campaigns.some((c) => c.uuid !== uuid && c.pillar_code === pillarCode && c.name === newName)) {
      fail(409, "DUPLICATE_NAME", `campaign '${newName}' ya existe dentro de pilar '${pillarCode}'.`, {
        entity_type: "campaign",
        name: newName,
        uniqueness_scope: `pilar '${pillarCode}'`,
      });
    }
    campaign.pillar_code = pillarCode;
    campaign.name = newName;
    return campaign;
  }

  const meta: CampaignMeta = {
    segmento: payload.meta?.segmento ?? campaign.meta.segmento,
    etapa: payload.meta?.etapa ?? campaign.meta.etapa,
    campus: payload.meta?.campus ?? campaign.meta.campus,
    medio: payload.meta?.medio ?? campaign.meta.medio,
    obj_camp: payload.meta?.obj_camp ?? campaign.meta.obj_camp,
    obj_plat: payload.meta?.obj_plat ?? campaign.meta.obj_plat,
    tipo_camp: payload.meta?.tipo_camp ?? campaign.meta.tipo_camp,
  };
  validateCampaignFields(meta, pillarCode);

  const newName = campaignName({ ...meta, pillar_code: pillarCode });
  if (newName !== campaign.name && campaigns.some((c) => c.uuid !== uuid && c.pillar_code === pillarCode && c.name === newName)) {
    fail(409, "DUPLICATE_NAME", `campaign '${newName}' ya existe dentro de pilar '${pillarCode}'.`, {
      entity_type: "campaign",
      name: newName,
      uniqueness_scope: `pilar '${pillarCode}'`,
    });
  }

  campaign.pillar_code = pillarCode;
  campaign.name = newName;
  campaign.meta = meta;
  return campaign;
}

export function deleteCampaign(uuid: string): void {
  const index = campaigns.findIndex((c) => c.uuid === uuid);
  if (index === -1) fail(404, "NOT_FOUND", "No encontrado.");
  campaigns.splice(index, 1);
}

export function duplicateCampaign(uuid: string): StoredCampaign {
  const original = getCampaignByUuid(uuid);
  if (!original) fail(404, "NOT_FOUND", "No encontrado.");

  const name = resolveAvailableName(
    original.name,
    (candidate) => campaigns.some((c) => c.pillar_code === original.pillar_code && c.name === candidate),
  );

  const copy: StoredCampaign = {
    ...original,
    id: nextId++,
    uuid: crypto.randomUUID(),
    name,
    ad_sets: original.ad_sets.map((adSet) => ({
      ...adSet,
      id: nextId++,
      uuid: crypto.randomUUID(),
      ads: adSet.ads.map((ad) => ({ ...ad, id: nextId++, uuid: crypto.randomUUID() })),
    })),
  };
  campaigns.push(copy);
  return copy;
}

// ---- Ad Set ---------------------------------------------------------------

export function createAdSet(campaignUuid: string, payload: { meta?: Partial<AdSetMeta>; weight?: number }): StoredAdSet {
  const campaign = getCampaignByUuid(campaignUuid);
  if (!campaign) fail(404, "NOT_FOUND", "No encontrado.");

  const meta: AdSetMeta = {
    edad: payload.meta?.edad ?? "",
    ubicacion: payload.meta?.ubicacion ?? "",
    facultad: payload.meta?.facultad ?? "",
    senal: payload.meta?.senal ?? "",
    detalle: payload.meta?.detalle ?? "",
  };
  validateAdSetFields(meta);

  const name = adSetName(meta);
  if (campaign.ad_sets.some((g) => g.name === name)) {
    fail(409, "DUPLICATE_NAME", `ad_set '${name}' ya existe dentro de campaña #${campaign.id}.`, {
      entity_type: "ad_set",
      name,
      uniqueness_scope: `campaña #${campaign.id}`,
    });
  }

  const adSet: StoredAdSet = {
    id: nextId++,
    uuid: crypto.randomUUID(),
    campaign_id: campaign.id,
    name,
    meta,
    ads: [],
  };
  campaign.ad_sets.push(adSet);
  return adSet;
}

function findAdSet(uuid: string): { campaign: StoredCampaign; adSet: StoredAdSet } {
  for (const campaign of campaigns) {
    const adSet = campaign.ad_sets.find((g) => g.uuid === uuid);
    if (adSet) return { campaign, adSet };
  }
  fail(404, "NOT_FOUND", "No encontrado.");
}

export function updateAdSet(
  uuid: string,
  payload: { meta?: Partial<AdSetMeta>; weight?: number; name?: string },
): StoredAdSet {
  const { campaign, adSet } = findAdSet(uuid);

  if (payload.name && payload.name.trim() !== "") {
    const newName = payload.name.trim();
    if (newName !== adSet.name && campaign.ad_sets.some((g) => g.uuid !== uuid && g.name === newName)) {
      fail(409, "DUPLICATE_NAME", `ad_set '${newName}' ya existe dentro de campaña #${campaign.id}.`, {
        entity_type: "ad_set",
        name: newName,
        uniqueness_scope: `campaña #${campaign.id}`,
      });
    }
    adSet.name = newName;
    return adSet;
  }

  const meta: AdSetMeta = {
    edad: payload.meta?.edad ?? adSet.meta.edad,
    ubicacion: payload.meta?.ubicacion ?? adSet.meta.ubicacion,
    facultad: payload.meta?.facultad ?? adSet.meta.facultad,
    senal: payload.meta?.senal ?? adSet.meta.senal,
    detalle: payload.meta?.detalle ?? adSet.meta.detalle,
  };
  validateAdSetFields(meta);

  const newName = adSetName(meta);
  if (newName !== adSet.name && campaign.ad_sets.some((g) => g.uuid !== uuid && g.name === newName)) {
    fail(409, "DUPLICATE_NAME", `ad_set '${newName}' ya existe dentro de campaña #${campaign.id}.`, {
      entity_type: "ad_set",
      name: newName,
      uniqueness_scope: `campaña #${campaign.id}`,
    });
  }

  adSet.name = newName;
  adSet.meta = meta;
  return adSet;
}

export function deleteAdSet(uuid: string): void {
  const { campaign, adSet } = findAdSet(uuid);
  campaign.ad_sets = campaign.ad_sets.filter((g) => g.uuid !== adSet.uuid);
}

export function duplicateAdSet(uuid: string): StoredAdSet {
  const { campaign, adSet } = findAdSet(uuid);
  const name = resolveAvailableName(
    adSet.name,
    (candidate) => campaign.ad_sets.some((g) => g.name === candidate),
  );
  const copy: StoredAdSet = {
    ...adSet,
    id: nextId++,
    uuid: crypto.randomUUID(),
    name,
    ads: adSet.ads.map((ad) => ({ ...ad, id: nextId++, uuid: crypto.randomUUID() })),
  };
  campaign.ad_sets.push(copy);
  return copy;
}

// ---- Ad ---------------------------------------------------------------------

export function createAd(adSetUuid: string, payload: { meta?: Partial<AdMeta>; url?: string; weight?: number }): StoredAd {
  const { adSet } = findAdSet(adSetUuid);

  const meta: AdMeta = {
    formato: payload.meta?.formato ?? "",
    concepto: payload.meta?.concepto ?? "",
    motivo: payload.meta?.motivo ?? "",
    mensaje: payload.meta?.mensaje ?? "",
    carrera: payload.meta?.carrera ?? "",
    fecha: payload.meta?.fecha ?? "",
  };
  validateAdFields(meta);

  const name = adName(meta);
  if (adSet.ads.some((a) => a.name === name)) {
    fail(409, "DUPLICATE_NAME", `ad '${name}' ya existe dentro de conjunto #${adSet.id}.`, {
      entity_type: "ad",
      name,
      uniqueness_scope: `conjunto #${adSet.id}`,
    });
  }

  const ad: StoredAd = {
    id: nextId++,
    uuid: crypto.randomUUID(),
    name,
    url: payload.url ?? null,
    meta,
  };
  adSet.ads.push(ad);
  return ad;
}

function findAd(uuid: string): { adSet: StoredAdSet; ad: StoredAd } {
  for (const campaign of campaigns) {
    for (const adSet of campaign.ad_sets) {
      const ad = adSet.ads.find((a) => a.uuid === uuid);
      if (ad) return { adSet, ad };
    }
  }
  fail(404, "NOT_FOUND", "No encontrado.");
}

export function updateAd(
  uuid: string,
  payload: { meta?: Partial<AdMeta>; url?: string; name?: string },
): StoredAd {
  const { adSet, ad } = findAd(uuid);

  if (payload.name && payload.name.trim() !== "") {
    const newName = payload.name.trim();
    if (newName !== ad.name && adSet.ads.some((a) => a.uuid !== uuid && a.name === newName)) {
      fail(409, "DUPLICATE_NAME", `ad '${newName}' ya existe dentro de conjunto #${adSet.id}.`, {
        entity_type: "ad",
        name: newName,
        uniqueness_scope: `conjunto #${adSet.id}`,
      });
    }
    ad.name = newName;
    if (payload.url !== undefined) {
      ad.url = payload.url;
    }
    return ad;
  }

  const meta: AdMeta = {
    formato: payload.meta?.formato ?? ad.meta.formato,
    concepto: payload.meta?.concepto ?? ad.meta.concepto,
    motivo: payload.meta?.motivo ?? ad.meta.motivo,
    mensaje: payload.meta?.mensaje ?? ad.meta.mensaje,
    carrera: payload.meta?.carrera ?? ad.meta.carrera,
    fecha: payload.meta?.fecha ?? ad.meta.fecha,
  };
  validateAdFields(meta);

  const newName = adName(meta);
  if (newName !== ad.name && adSet.ads.some((a) => a.uuid !== uuid && a.name === newName)) {
    fail(409, "DUPLICATE_NAME", `ad '${newName}' ya existe dentro de conjunto #${adSet.id}.`, {
      entity_type: "ad",
      name: newName,
      uniqueness_scope: `conjunto #${adSet.id}`,
    });
  }

  ad.name = newName;
  ad.meta = meta;
  if (payload.url !== undefined) {
    ad.url = payload.url;
  }
  return ad;
}

export function deleteAd(uuid: string): void {
  const { adSet, ad } = findAd(uuid);
  adSet.ads = adSet.ads.filter((a) => a.uuid !== ad.uuid);
}

export function duplicateAd(uuid: string): StoredAd {
  const { adSet, ad } = findAd(uuid);
  const name = resolveAvailableName(ad.name, (candidate) => adSet.ads.some((a) => a.name === candidate));
  const copy: StoredAd = { ...ad, id: nextId++, uuid: crypto.randomUUID(), name };
  adSet.ads.push(copy);
  return copy;
}
