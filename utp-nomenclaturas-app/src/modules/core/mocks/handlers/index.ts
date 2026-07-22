import { adHandlers } from "@/modules/core/mocks/handlers/ads";
import { adSetHandlers } from "@/modules/core/mocks/handlers/ad-sets";
import { campaignHandlers } from "@/modules/core/mocks/handlers/campaigns";
import { configHandlers } from "@/modules/core/mocks/handlers/config";
import { exportHandlers } from "@/modules/core/mocks/handlers/export";
import { utmHandlers } from "@/modules/core/mocks/handlers/utms";

export const handlers = [
  ...configHandlers,
  ...campaignHandlers,
  ...adSetHandlers,
  ...adHandlers,
  ...utmHandlers,
  ...exportHandlers,
];
