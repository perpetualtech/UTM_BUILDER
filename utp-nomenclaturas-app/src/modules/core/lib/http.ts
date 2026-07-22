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

/**
 * Descarga un archivo (Excel/backup JSON) — `http.get()` no sirve porque
 * siempre parsea el body como JSON; acá el body es binario (o JSON servido
 * como adjunto). Lee el filename de `Content-Disposition` cuando está.
 */
export async function fetchFile(path: string): Promise<{ blob: Blob; filename: string }> {
  const response = await fetch(`${API_BASE}${path}`);

  if (!response.ok) {
    const body = (await response.json()) as ApiErrorBody;
    throw new ApiError(response.status, body);
  }

  const disposition = response.headers.get("Content-Disposition") ?? "";
  const match = /filename="?([^"]+)"?/.exec(disposition);

  return { blob: await response.blob(), filename: match?.[1] ?? "download" };
}

/** Dispara la descarga de un Blob en el navegador — sin dependencias extra. */
export function triggerDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}
