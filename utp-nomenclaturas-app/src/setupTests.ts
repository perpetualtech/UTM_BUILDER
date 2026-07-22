import "@testing-library/jest-dom";
import { afterAll, afterEach, beforeAll } from "vitest";
import { server } from "@/modules/core/mocks/server";
import { resetStore } from "@/modules/core/mocks/store";

beforeAll(() => server.listen({ onUnhandledRequest: "warn" }));
afterEach(() => {
  server.resetHandlers();
  resetStore();
});
afterAll(() => server.close());
