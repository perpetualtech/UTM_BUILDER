import { http } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import { createAdSet, deleteAdSet, duplicateAdSet, updateAdSet } from "@/modules/core/mocks/store";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const adSetHandlers = [
  http.post(`${API_BASE}/campaigns/:uuid/ad-sets`, async ({ params, request }) => {
    const payload = await request.json();
    return respond(
      () => createAdSet(params.uuid as string, payload as Parameters<typeof createAdSet>[1]),
      201,
    );
  }),

  http.patch(`${API_BASE}/ad-sets/:uuid`, async ({ params, request }) => {
    const payload = await request.json();
    return respond(() =>
      updateAdSet(params.uuid as string, payload as Parameters<typeof updateAdSet>[1]),
    );
  }),

  http.delete(`${API_BASE}/ad-sets/:uuid`, ({ params }) =>
    respond(() => deleteAdSet(params.uuid as string), 204),
  ),

  http.post(`${API_BASE}/ad-sets/:uuid/duplicate`, ({ params }) =>
    respond(() => duplicateAdSet(params.uuid as string), 201),
  ),
];
