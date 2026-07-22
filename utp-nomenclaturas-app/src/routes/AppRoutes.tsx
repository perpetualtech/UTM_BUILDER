import { Route, Routes } from "react-router-dom";
import { Shell } from "@/shell/Shell";
import { DashboardPage } from "@/modules/dashboard/pages/DashboardPage";
import { BuilderPage } from "@/modules/builder/pages/BuilderPage";
import { RepositoryPage } from "@/modules/repository/pages/RepositoryPage";

/**
 * §8.2 del SDD — solo las 3 vistas de Fase 2. UTM/Export/Config/Dictionary
 * se agregan en fases posteriores, no como placeholders.
 */
export function AppRoutes() {
  return (
    <Routes>
      <Route element={<Shell />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/builder" element={<BuilderPage />} />
        <Route path="/repository" element={<RepositoryPage />} />
      </Route>
    </Routes>
  );
}
