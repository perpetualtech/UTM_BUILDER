import { Outlet, useLocation } from "react-router-dom";
import { Sidebar } from "@/shell/Sidebar";
import { Topbar } from "@/shell/Topbar";

const TITLES: Record<string, string> = {
  "/": "Inicio",
  "/builder": "Constructor",
  "/repository": "Repositorio",
  "/utm": "UTMs",
  "/export": "Exportar",
  "/config": "Configuración",
  "/dictionary": "Diccionario",
};

/** §8.2 del SDD: App → Shell → Sidebar, Topbar, Router. */
export function Shell() {
  const location = useLocation();
  const title = TITLES[location.pathname] ?? "UTP Nomenclaturas";

  return (
    <div className="flex h-dvh bg-background">
      <Sidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <Topbar title={title} />
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
