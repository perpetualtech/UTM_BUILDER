import { useMemo } from "react";
import {
  adName,
  adSetName,
  campaignName,
  type AdNameFields,
  type AdSetNameFields,
  type CampaignNameFields,
} from "@/modules/core/lib/nameBuilder";

/**
 * Preview local del nombre derivado (§3.1/§8.4 del SDD) — el servidor
 * (mock hoy, Drupal real en Fase 4) revalida y deriva el nombre autoritativo
 * al guardar (ADR-003); esto es solo feedback instantáneo en el formulario.
 *
 * Los hooks dependen de cada campo primitivo (no del objeto `fields`) a
 * propósito: el formulario pasa un objeto literal nuevo en cada render, así
 * que depender de `fields` invalidaría el memo siempre — los primitivos son
 * justo lo que determina el resultado.
 */
export function useCampaignNamePreview(fields: CampaignNameFields): string {
  return useMemo(() => {
    try {
      return campaignName(fields);
    } catch {
      return "";
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fields.segmento, fields.etapa, fields.campus, fields.medio, fields.obj_camp, fields.obj_plat, fields.tipo_camp, fields.pillar_code]);
}

export function useAdSetNamePreview(fields: AdSetNameFields): string {
  return useMemo(
    () => adSetName(fields),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [fields.edad, fields.ubicacion, fields.facultad, fields.senal, fields.detalle],
  );
}

export function useAdNamePreview(fields: AdNameFields): string {
  return useMemo(
    () => adName(fields),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [fields.formato, fields.concepto, fields.motivo, fields.mensaje, fields.carrera, fields.fecha],
  );
}
