import { adHandlers } from "@/modules/core/mocks/handlers/ads";
import { adSetHandlers } from "@/modules/core/mocks/handlers/ad-sets";
import { campaignHandlers } from "@/modules/core/mocks/handlers/campaigns";
import { configHandlers } from "@/modules/core/mocks/handlers/config";

export const handlers = [...configHandlers, ...campaignHandlers, ...adSetHandlers, ...adHandlers];
