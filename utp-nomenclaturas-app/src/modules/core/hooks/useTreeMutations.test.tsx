import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { useCreateAdSet, useCreateCampaign } from "@/modules/core/hooks/useTreeMutations";
import { ApiError } from "@/modules/core/types/api";

/**
 * Integración real: mutación TanStack Query → fetch() → MSW → validación
 * del store (misma lógica que TreeManager, Fase 1 PHP) → respuesta →
 * ApiError en cliente. Mismos escenarios que
 * utp_nomenclaturas/tests/src/Kernel/TreeControllerValidationTest.php.
 */
function wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

const VALID_CAMPAIGN_PAYLOAD = {
  pillar_code: "calidad",
  meta: {
    segmento: "jovenes",
    etapa: "upper",
    campus: "lima",
    medio: "Meta",
    obj_camp: "Awareness",
    obj_plat: "Alcance",
    tipo_camp: "Video",
  },
};

describe("useCreateCampaign", () => {
  it("crea una campaña válida (201) con el nombre derivado esperado", async () => {
    const { result } = renderHook(() => useCreateCampaign(), { wrapper });
    result.current.mutate(VALID_CAMPAIGN_PAYLOAD);

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data?.name).toBe("jovenes_upper_lima_Meta_Awareness_Alcance_Video_calidad");
  });

  it("nombre duplicado dentro del mismo pilar responde 409", async () => {
    const { result: first } = renderHook(() => useCreateCampaign(), { wrapper });
    first.current.mutate(VALID_CAMPAIGN_PAYLOAD);
    await waitFor(() => expect(first.current.isSuccess).toBe(true));

    const { result: second } = renderHook(() => useCreateCampaign(), { wrapper });
    second.current.mutate(VALID_CAMPAIGN_PAYLOAD);
    await waitFor(() => expect(second.current.isError).toBe(true));

    const error = second.current.error as ApiError;
    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(409);
    expect(error.body.code).toBe("DUPLICATE_NAME");
  });

  it("condicional D1 violado responde 422", async () => {
    const { result } = renderHook(() => useCreateCampaign(), { wrapper });
    result.current.mutate({
      ...VALID_CAMPAIGN_PAYLOAD,
      meta: { ...VALID_CAMPAIGN_PAYLOAD.meta, etapa: "lower", medio: "DV360" },
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
    const error = result.current.error as ApiError;
    expect(error.status).toBe(422);
    expect(error.body.code).toBe("VALIDATION_FAILED");
    expect(error.body.details.conditional_id).toBe("D1");
  });

  it("condicional D2 violado responde 422 (empleabilidad exclusivo de jovenes)", async () => {
    const { result } = renderHook(() => useCreateCampaign(), { wrapper });
    result.current.mutate({
      pillar_code: "empleabilidad",
      meta: { ...VALID_CAMPAIGN_PAYLOAD.meta, segmento: "adultos" },
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
    const error = result.current.error as ApiError;
    expect(error.status).toBe(422);
    expect(error.body.details.conditional_id).toBe("D2");
  });
});

describe("useCreateAdSet", () => {
  it("condicional D3 violado responde 422 (facultad no disponible en la ubicación)", async () => {
    const { result: campaignResult } = renderHook(() => useCreateCampaign(), { wrapper });
    campaignResult.current.mutate(VALID_CAMPAIGN_PAYLOAD);
    await waitFor(() => expect(campaignResult.current.isSuccess).toBe(true));
    const campaignUuid = campaignResult.current.data!.uuid;

    const { result } = renderHook(() => useCreateAdSet(campaignUuid), { wrapper });
    result.current.mutate({
      meta: { edad: "j1", ubicacion: "lima-norte", facultad: "med", senal: "broad", detalle: "" },
    });

    await waitFor(() => expect(result.current.isError).toBe(true));
    const error = result.current.error as ApiError;
    expect(error.status).toBe(422);
    expect(error.body.details.conditional_id).toBe("D3");
  });
});
