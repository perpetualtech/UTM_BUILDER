import { NavLink } from "react-router-dom";
import { cn } from "@/modules/core/lib/utils";

/**
 * §8.2 del SDD: Shell → Sidebar. 5 vistas: Fase 2 (Dashboard/Builder/
 * Repository) + Fase 3 (UTM/Export). Config/Dictionary llegan en Fase 4 y
 * se agregan acá recién cuando tengan una ruta real.
 */
const NAV_ITEMS = [
  { to: "/", label: "Inicio" },
  { to: "/builder", label: "Constructor" },
  { to: "/repository", label: "Repositorio" },
  { to: "/utm", label: "UTMs" },
  { to: "/export", label: "Exportar" },
];

export function Sidebar() {
  return (
    <aside className="flex w-56 shrink-0 flex-col bg-sidebar text-sidebar-foreground">
      <div className="px-4 py-5 text-lg font-semibold tracking-tight">UTP Nomenclaturas</div>
      <nav className="flex flex-col gap-1 px-2">
        {NAV_ITEMS.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === "/"}
            className={({ isActive }) =>
              cn(
                "rounded-md px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-sidebar-primary text-sidebar-primary-foreground"
                  : "text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
              )
            }
          >
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
