import path from "path"
import tailwindcss from "@tailwindcss/vite"
import react from "@vitejs/plugin-react"
import { defineConfig } from "vitest/config"

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  test: {
    // jsdom 27 rompe en Vitest (ERR_REQUIRE_ESM en una dependencia
    // transitiva de @asamuzakjp/css-color) — happy-dom es el reemplazo
    // estándar en el ecosistema Vitest, más liviano y sin ese conflicto.
    environment: "happy-dom",
    globals: true,
    setupFiles: ["./src/setupTests.ts"],
  },
})
