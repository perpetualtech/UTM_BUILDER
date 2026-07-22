import { http } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import { createAd, deleteAd, duplicateAd, updateAd } from "@/modules/core/mocks/store";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const adHandlers = [
  http.post(`${API_BASE}/ad-sets/:uuid/ads`, async ({ params, request }) => {
    const payload = await request.json();
    return respond(() => createAd(params.uuid as string, payload as Parameters<typeof createAd>[1]), 201);
  }),

  http.patch(`${API_BASE}/ads/:uuid`, async ({ params, request }) => {
    const payload = await request.json();
    return respond(() => updateAd(params.uuid as string, payload as Parameters<typeof updateAd>[1]));
  }),

  http.delete(`${API_BASE}/ads/:uuid`, ({ params }) => respond(() => deleteAd(params.uuid as string), 204)),

  http.post(`${API_BASE}/ads/:uuid/duplicate`, ({ params }) =>
    respond(() => duplicateAd(params.uuid as string), 201),
  ),
];
