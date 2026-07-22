import { http, HttpResponse } from "msw";
import { seedDictionary } from "@/modules/core/mocks/seedDictionary";

const API_BASE = "/api/utp-nomenclaturas/v1";

export const configHandlers = [
  http.get(`${API_BASE}/config`, () => HttpResponse.json(seedDictionary)),
];
