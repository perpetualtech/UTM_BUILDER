import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/modules/core/components/design-system/sonner";
import { queryClient } from "@/modules/core/lib/queryClient";
import "./index.css";
import App from "./App.tsx";

/**
 * MSW solo se carga en dev (import dinámico → no entra al bundle de
 * producción). En Fase 4, contra el Drupal real, esto no se ejecuta y la
 * app llama directo a la API real.
 */
async function enableMocking() {
  if (!import.meta.env.DEV) return;
  const { worker } = await import("@/modules/core/mocks/browser");
  return worker.start({ onUnhandledRequest: "bypass" });
}

enableMocking().then(() => {
  createRoot(document.getElementById("root")!).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <App />
        <Toaster position="top-right" />
      </QueryClientProvider>
    </StrictMode>,
  );
});
