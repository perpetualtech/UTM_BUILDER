import { ApiError, type ApiErrorBody } from "@/modules/core/types/api";

/**
 * Base de la API (§7 del SDD). En Fase 4, cuando se monte en Drupal, esta
 * variable de entorno apunta al sitio real; hoy MSW intercepta las mismas
 * rutas en dev/test.
 */
const API_BASE = import.meta.env.VITE_API_BASE ?? "/api/utp-nomenclaturas/v1";

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...init?.headers,
    },
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const body = await response.json();

  if (!response.ok) {
    throw new ApiError(response.status, body as ApiErrorBody);
  }

  return body as T;
}

export const http = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: "POST", body: data ? JSON.stringify(data) : undefined }),
  patch: <T>(path: string, data?: unknown) =>
    request<T>(path, { method: "PATCH", body: data ? JSON.stringify(data) : undefined }),
  delete: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};
