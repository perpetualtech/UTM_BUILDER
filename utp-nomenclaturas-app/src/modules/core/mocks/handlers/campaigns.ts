import { http } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import {
  MockApiError,
  createCampaign,
  deleteCampaign,
  duplicateCampaign,
  getCampaignByUuid,
  listCampaigns,
  updateCampaign,
} from "@/modules/core/mocks/store";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const campaignHandlers = [
  http.get(`${API_BASE}/campaigns`, ({ request }) => {
    const url = new URL(request.url);
    return respond(() =>
      listCampaigns({
        pillar: url.searchParams.get("pillar") ?? undefined,
        medio: url.searchParams.get("medio") ?? undefined,
        q: url.searchParams.get("q") ?? undefined,
      }),
    );
  }),

  http.get(`${API_BASE}/campaigns/:uuid`, ({ params, request }) => {
    const url = new URL(request.url);
    const include = url.searchParams.get("include") ?? "";
    return respond(() => {
      const campaign = getCampaignByUuid(params.uuid as string);
      if (!campaign) throw new MockApiError(404, "NOT_FOUND", "No encontrado.");

      const { ad_sets, ...rest } = campaign;
      if (!include.includes("ad_sets")) return rest;

      const includeAds = include.includes("ads");
      return {
        ...rest,
        ad_sets: ad_sets.map(({ ads, campaign_id, ...adSet }) => ({
          ...adSet,
          ...(includeAds ? { ads } : {}),
        })),
      };
    });
  }),

  http.post(`${API_BASE}/campaigns`, async ({ request }) => {
    const payload = await request.json();
    return respond(() => createCampaign(payload as Parameters<typeof createCampaign>[0]), 201);
  }),

  http.patch(`${API_BASE}/campaigns/:uuid`, async ({ params, request }) => {
    const payload = await request.json();
    return respond(() =>
      updateCampaign(params.uuid as string, payload as Parameters<typeof updateCampaign>[1]),
    );
  }),

  http.delete(`${API_BASE}/campaigns/:uuid`, ({ params }) =>
    respond(() => deleteCampaign(params.uuid as string), 204),
  ),

  http.post(`${API_BASE}/campaigns/:uuid/duplicate`, ({ params }) =>
    respond(() => duplicateCampaign(params.uuid as string), 201),
  ),
];
