import { http } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import {
  createManualUtm,
  deleteManualUtm,
  flattenPaidUtms,
  getUtmConfig,
  listManualUtms,
  updateUtmConfig,
} from "@/modules/core/mocks/store";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const utmHandlers = [
  http.get(`${API_BASE}/utms/paid`, () => respond(() => flattenPaidUtms())),

  http.get(`${API_BASE}/utms/config`, () => respond(() => getUtmConfig())),

  http.patch(`${API_BASE}/utms/config`, async ({ request }) => {
    const payload = await request.json();
    return respond(() => updateUtmConfig(payload as Parameters<typeof updateUtmConfig>[0]));
  }),

  http.get(`${API_BASE}/utms/manual`, () => respond(() => listManualUtms())),

  http.post(`${API_BASE}/utms/manual`, async ({ request }) => {
    const payload = await request.json();
    return respond(() => createManualUtm(payload as Parameters<typeof createManualUtm>[0]), 201);
  }),

  http.delete(`${API_BASE}/utms/manual/:uuid`, ({ params }) =>
    respond(() => deleteManualUtm(params.uuid as string), 204),
  ),
];
