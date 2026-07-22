import { KpiTile } from "@/modules/core/components/KpiTile";
import { useDashboardStats } from "@/modules/dashboard/hooks/useDashboardStats";

/** §4/§8.2 del SDD — Dashboard con los 4 KPIs recalculados en cada mutación. */
export function DashboardPage() {
  const { stats, isLoading } = useDashboardStats();

  return (
    <div className="flex flex-col gap-6">
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <KpiTile label="Campañas" value={isLoading ? 0 : stats.campanas} />
        <KpiTile label="Conjuntos" value={isLoading ? 0 : stats.conjuntos} />
        <KpiTile label="Anuncios" value={isLoading ? 0 : stats.anuncios} />
        <KpiTile label="Plataformas" value={isLoading ? 0 : stats.plataformas} />
      </div>
      {!isLoading && stats.campanas === 0 ? (
        <p className="text-sm text-muted-foreground">
          Todavía no hay campañas. Crea la primera en el Constructor.
        </p>
      ) : null}
    </div>
  );
}
