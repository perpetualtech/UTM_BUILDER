import { http, HttpResponse } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import {
  MockApiError,
  addListValue,
  deleteListValue,
  getDictionary,
  updateCampusFacultad,
  updateEtapaOptions,
  updateSegmentoPilar,
} from "@/modules/core/mocks/store";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const configHandlers = [
  http.get(`${API_BASE}/config`, () => HttpResponse.json(getDictionary())),

  // ---- Config Nivel 1/2/3 (Fase 4, §7.1) ---------------------------------

  http.post(`${API_BASE}/config/lists/:list_key/values`, async ({ params, request }) => {
    const { value } = (await request.json()) as { value?: string };
    return respond(() => {
      addListValue(params.list_key as string, (value ?? "").trim());
      return { list_key: params.list_key, value };
    }, 201);
  }),

  http.delete(`${API_BASE}/config/lists/:list_key/values/:code`, ({ params }) =>
    respond(() => {
      const removed = deleteListValue(params.list_key as string, params.code as string);
      if (!removed) {
        throw new MockApiError(404, "NOT_FOUND", "No encontrado.");
      }
    }, 204),
  ),

  http.put(`${API_BASE}/config/etapa-options/:etapa/:field`, async ({ params, request }) => {
    const { values } = (await request.json()) as { values?: string[] };
    return respond(() => {
      updateEtapaOptions(
        params.etapa as string,
        params.field as "medio" | "objCamp" | "objPlat" | "tipoCamp",
        values ?? [],
      );
      return { etapa: params.etapa, field: params.field, values };
    });
  }),

  http.put(`${API_BASE}/config/segmento-pilar/:segmento`, async ({ params, request }) => {
    const { pilares } = (await request.json()) as { pilares?: string[] };
    return respond(() => {
      updateSegmentoPilar(params.segmento as string, pilares ?? []);
      return { segmento: params.segmento, pilares };
    });
  }),

  http.put(`${API_BASE}/config/campus-facultad`, async ({ request }) => {
    const { matrix } = (await request.json()) as { matrix?: Record<string, string[]> };
    return respond(() => {
      updateCampusFacultad(matrix ?? {});
      return getDictionary().campus_facultad;
    });
  }),
];
