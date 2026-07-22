import { Route, Routes } from "react-router-dom";
import { Shell } from "@/shell/Shell";
import { DashboardPage } from "@/modules/dashboard/pages/DashboardPage";
import { BuilderPage } from "@/modules/builder/pages/BuilderPage";
import { RepositoryPage } from "@/modules/repository/pages/RepositoryPage";
import { UtmPage } from "@/modules/utm/pages/UtmPage";
import { ExportPage } from "@/modules/export/pages/ExportPage";
import { ConfigPage } from "@/modules/config/pages/ConfigPage";
import { DictionaryPage } from "@/modules/dictionary/pages/DictionaryPage";

/**
 * §8.2 del SDD — 7 vistas: Fase 2 (Dashboard/Builder/Repository) + Fase 3
 * (UTM/Export) + Fase 4 (Config/Dictionary). El árbol de componentes del
 * SDD queda completo — no quedan vistas fuera de alcance.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route element={<Shell />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/builder" element={<BuilderPage />} />
        <Route path="/repository" element={<RepositoryPage />} />
        <Route path="/utm" element={<UtmPage />} />
        <Route path="/export" element={<ExportPage />} />
        <Route path="/config" element={<ConfigPage />} />
        <Route path="/dictionary" element={<DictionaryPage />} />
      </Route>
    </Routes>
  );
}
