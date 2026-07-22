import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { useCreateManualUtm, useDeleteManualUtm, useManualUtms } from "@/modules/utm/hooks/useManualUtm";
import { ApiError } from "@/modules/core/types/api";

/**
 * Integración real (mutación → fetch → MSW → store), mismos escenarios que
 * utp_nomenclaturas/tests/src/Kernel/ManualUtmValidationTest.php (Fase 3).
 */
function wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

const VALID_PAYLOAD = {
  utm_source: "instagram",
  utm_medium: "influencer",
  utm_campaign: "lo-que-el-mar-se-llevo",
  url: "https://instagram.com/utp.oficial",
  qs: "utm_source=instagram&utm_medium=influencer&utm_campaign=lo-que-el-mar-se-llevo",
};

describe("useCreateManualUtm", () => {
  it("crea una UTM manual válida (201)", async () => {
    const { result } = renderHook(() => useCreateManualUtm(), { wrapper });
    result.current.mutate(VALID_PAYLOAD);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.utm_source).toBe("instagram");
    expect(result.current.data?.uuid).toBeTruthy();
  });

  it.each(["utm_source", "utm_medium", "url", "qs"] as const)("sin %s responde 422", async (missingField) => {
    const payload = { ...VALID_PAYLOAD, [missingField]: "" };
    const { result } = renderHook(() => useCreateManualUtm(), { wrapper });
    result.current.mutate(payload);

    await waitFor(() => expect(result.current.isError).toBe(true));
    const error = result.current.error as ApiError;
    expect(error.status).toBe(422);
    expect(error.body.code).toBe("VALIDATION_FAILED");
    const fields = (error.body.details.violations as Array<{ field: string }>).map((v) => v.field);
    expect(fields).toContain(missingField);
  });
});

describe("useManualUtms / useDeleteManualUtm", () => {
  it("lista más reciente primero y elimina correctamente", async () => {
    const { result: create } = renderHook(() => useCreateManualUtm(), { wrapper });
    create.current.mutate(VALID_PAYLOAD);
    await waitFor(() => expect(create.current.isSuccess).toBe(true));

    const { result: create2 } = renderHook(() => useCreateManualUtm(), { wrapper });
    create2.current.mutate({ ...VALID_PAYLOAD, utm_source: "youtube" });
    await waitFor(() => expect(create2.current.isSuccess).toBe(true));

    const { result: list } = renderHook(() => useManualUtms(), { wrapper });
    await waitFor(() => expect(list.current.isSuccess).toBe(true));
    expect(list.current.data).toHaveLength(2);
    expect(list.current.data?.[0].utm_source).toBe("youtube");

    const { result: del } = renderHook(() => useDeleteManualUtm(), { wrapper });
    del.current.mutate(create2.current.data!.uuid);
    await waitFor(() => expect(del.current.isSuccess).toBe(true));
  });
});
