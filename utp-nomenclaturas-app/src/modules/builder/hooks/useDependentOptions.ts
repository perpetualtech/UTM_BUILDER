import { useMemo } from "react";
import {
  getFacultadesForUbicacion,
  getOptionsForEtapa,
  getPilaresForSegmento,
} from "@/modules/core/lib/dictionaryRules";
import type { DictBundle } from "@/modules/core/types/api";

/**
 * D1/D2 (§3.2 del SDD) resueltos en cliente para poblar/deshabilitar los
 * selects de Campaign — §8.4: "useDependentOptions(level, currentValues)".
 * Ad no tiene condicionales (solo Campaign y AdSet), así que no aplica ahí.
 */
export function useCampaignDependentOptions(
  bundle: DictBundle | undefined,
  values: { segmento: string; etapa: string },
) {
  return useMemo(() => {
    if (!bundle) {
      return { pilarOptions: [], medioOptions: [], objCampOptions: [], objPlatOptions: [], tipoCampOptions: [] };
    }
    const pilarOptions = values.segmento ? getPilaresForSegmento(bundle, values.segmento) : [];
    const etapaSelected = Boolean(values.etapa);
    return {
      pilarOptions,
      medioOptions: etapaSelected ? getOptionsForEtapa(bundle, values.etapa, "medio") : [],
      objCampOptions: etapaSelected ? getOptionsForEtapa(bundle, values.etapa, "objCamp") : [],
      objPlatOptions: etapaSelected ? getOptionsForEtapa(bundle, values.etapa, "objPlat") : [],
      tipoCampOptions: etapaSelected ? getOptionsForEtapa(bundle, values.etapa, "tipoCamp") : [],
    };
  }, [bundle, values.segmento, values.etapa]);
}

/** D3 (§3.2 del SDD): facultades disponibles según la ubicación elegida. */
export function useAdSetDependentOptions(bundle: DictBundle | undefined, values: { ubicacion: string }) {
  return useMemo(() => {
    if (!bundle || !values.ubicacion) {
      return { facultadOptions: bundle?.lists.facultad ?? [] };
    }
    return { facultadOptions: getFacultadesForUbicacion(bundle, values.ubicacion) };
  }, [bundle, values.ubicacion]);
}
