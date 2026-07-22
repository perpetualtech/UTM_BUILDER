import { QueryClient } from "@tanstack/react-query";

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60, // 1 min — el árbol cambia seguido mientras se construye
      retry: false, // los 404/409/422 del backend son respuestas válidas, no fallas de red
    },
  },
});
