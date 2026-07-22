import path from "path"
import tailwindcss from "@tailwindcss/vite"
import react from "@vitejs/plugin-react"
import { defineConfig } from "vite"

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  build: {
    // AppController.php lee dist/.vite/manifest.json para montar la SPA
    // en Drupal con los nombres de archivo hasheados reales.
    manifest: true,
  },
})
