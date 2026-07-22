import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { isFacultadValidForUbicacion } from "@/modules/core/lib/dictionaryRules";
import { ApiError } from "@/modules/core/types/api";
import {
  useAddListValue,
  useDeleteListValue,
  useUpdateCampusFacultad,
  useUpdateEtapaOptions,
  useUpdateSegmentoPilar,
} from "@/modules/config/hooks/useConfigMutations";

/**
 * Integración real (mutación → fetch → MSW → store mutable), mismos
 * escenarios que utp_nomenclaturas/tests/src/Kernel/ConfigControllerTest.php
 * (Fase 4) — incluido el AC "editar la matriz impacta D3 en vivo".
 */
function wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("useAddListValue / useDeleteListValue", () => {
  it("agrega y luego elimina un valor de una lista editable", async () => {
    const { result: add } = renderHook(() => useAddListValue(), { wrapper });
    add.current.mutate({ listKey: "campus", value: "nueva-sede" });
    await waitFor(() => expect(add.current.isSuccess).toBe(true));

    const { result: bundle } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(bundle.current.isSuccess).toBe(true));
    expect(bundle.current.data?.lists.campus).toContain("nueva-sede");

    const { result: del } = renderHook(() => useDeleteListValue(), { wrapper });
    del.current.mutate({ listKey: "campus", value: "nueva-sede" });
    await waitFor(() => expect(del.current.isSuccess).toBe(true));
  });

  it("agregar un valor duplicado responde 409", async () => {
    const { result } = renderHook(() => useAddListValue(), { wrapper });
    result.current.mutate({ listKey: "campus", value: "lima" });
    await waitFor(() => expect(result.current.isError).toBe(true));
    expect((result.current.error as ApiError).status).toBe(409);
  });

  it("agregar a una lista no editable responde 422", async () => {
    const { result } = renderHook(() => useAddListValue(), { wrapper });
    result.current.mutate({ listKey: "etapa", value: "x" });
    await waitFor(() => expect(result.current.isError).toBe(true));
    expect((result.current.error as ApiError).status).toBe(422);
  });
});

describe("useUpdateEtapaOptions — D1 en vivo", () => {
  it("el nuevo valor aparece en el próximo GET /config", async () => {
    const { result: mutate } = renderHook(() => useUpdateEtapaOptions(), { wrapper });
    mutate.current.mutate({ etapa: "upper", field: "medio", values: ["Meta", "Snapchat"] });
    await waitFor(() => expect(mutate.current.isSuccess).toBe(true));

    const { result: bundle } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(bundle.current.isSuccess).toBe(true));
    expect(bundle.current.data?.etapa_conditionals.medio.upper).toEqual(["Meta", "Snapchat"]);
  });
});

describe("useUpdateSegmentoPilar — D2 en vivo", () => {
  it("el bundle refleja el nuevo array de pilares", async () => {
    const { result: mutate } = renderHook(() => useUpdateSegmentoPilar(), { wrapper });
    mutate.current.mutate({ segmento: "adultos", pilares: ["calidad"] });
    await waitFor(() => expect(mutate.current.isSuccess).toBe(true));

    const { result: bundle } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(bundle.current.isSuccess).toBe(true));
    expect(bundle.current.data?.segmento_pilar.adultos).toEqual(["calidad"]);
  });
});

describe("useUpdateCampusFacultad — D3 en vivo (AC principal de la Fase 4)", () => {
  it("restringir 'ing' a Lima hace que deje de estar disponible en Arequipa", async () => {
    const { result: before } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(before.current.isSuccess).toBe(true));
    expect(isFacultadValidForUbicacion(before.current.data!, "ing", "arequipa")).toBe(true);

    const { result: mutate } = renderHook(() => useUpdateCampusFacultad(), { wrapper });
    mutate.current.mutate({ ing: ["lima-centro", "lima-norte"] });
    await waitFor(() => expect(mutate.current.isSuccess).toBe(true));

    const { result: after } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(after.current.isSuccess).toBe(true));
    expect(isFacultadValidForUbicacion(after.current.data!, "ing", "arequipa")).toBe(false);
    expect(isFacultadValidForUbicacion(after.current.data!, "ing", "lima-centro")).toBe(true);
  });

  it("cubrir todas las sedes específicas elimina la restricción existente", async () => {
    const { result: bundle } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(bundle.current.isSuccess).toBe(true));
    const todasLasSedes = bundle.current.data!.sedes_especificas;

    const { result: mutate } = renderHook(() => useUpdateCampusFacultad(), { wrapper });
    mutate.current.mutate({ com: todasLasSedes });
    await waitFor(() => expect(mutate.current.isSuccess).toBe(true));

    const { result: after } = renderHook(() => useDictBundle(), { wrapper });
    await waitFor(() => expect(after.current.isSuccess).toBe(true));
    expect(after.current.data?.campus_facultad.com).toBeUndefined();
    expect(isFacultadValidForUbicacion(after.current.data!, "com", "arequipa")).toBe(true);
  });
});
