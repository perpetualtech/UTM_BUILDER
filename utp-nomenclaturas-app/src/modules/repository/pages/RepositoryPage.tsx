import { useMemo, useState } from "react";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { useCampaigns } from "@/modules/core/hooks/useCampaigns";
import { CampaignRow } from "@/modules/repository/components/CampaignRow";

/** §7.2/§8.2 del SDD: Repository — árbol filtrable (pillar/medio/q). */
export function RepositoryPage() {
  const { data: bundle } = useDictBundle();
  const [pillar, setPillar] = useState("");
  const [medio, setMedio] = useState("");
  const [q, setQ] = useState("");

  const { data: campaigns, isLoading } = useCampaigns({
    pillar: pillar || undefined,
    medio: medio || undefined,
    q: q || undefined,
  });

  const pillarOptions = useMemo(() => {
    if (!bundle) return [];
    return Array.from(new Set(Object.values(bundle.segmento_pilar).flat()));
  }, [bundle]);

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-3 gap-4">
        <FieldSelect label="Pilar" value={pillar} options={pillarOptions} onChange={setPillar} placeholder="Todos" />
        <FieldSelect
          label="Medio"
          value={medio}
          options={bundle?.platforms ?? []}
          onChange={setMedio}
          placeholder="Todos"
        />
        <div className="flex flex-col gap-1.5">
          <label htmlFor="repository-search" className="text-sm font-medium text-foreground">
            Buscar
          </label>
          <Input id="repository-search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre de campaña…" />
        </div>
      </div>

      {isLoading ? (
        <p className="text-sm text-muted-foreground">Cargando…</p>
      ) : !campaigns || campaigns.length === 0 ? (
        <p className="text-sm text-muted-foreground">Sin resultados. Crea nomenclaturas en el Constructor.</p>
      ) : (
        <div className="flex flex-col gap-2">
          {campaigns.map((campaign) => (
            <CampaignRow key={campaign.uuid} campaign={campaign} />
          ))}
        </div>
      )}
    </div>
  );
}
