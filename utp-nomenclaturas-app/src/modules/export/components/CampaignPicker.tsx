import { useMemo, useState } from "react";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { useCampaigns } from "@/modules/core/hooks/useCampaigns";

interface CampaignPickerProps {
  selected: Set<string>;
  onSelectedChange: (selected: Set<string>) => void;
}

/**
 * §3.5 del SDD: selección de campañas para el Excel de nomenclaturas —
 * equivalente a `getExportFiltered()`/`toggleExportCamp()` del HTML.
 */
export function CampaignPicker({ selected, onSelectedChange }: CampaignPickerProps) {
  const { data: bundle } = useDictBundle();
  const [pillar, setPillar] = useState("");
  const [medio, setMedio] = useState("");
  const [q, setQ] = useState("");

  const { data: campaigns } = useCampaigns({ pillar: pillar || undefined, medio: medio || undefined, q: q || undefined });

  const pillarOptions = useMemo(() => {
    if (!bundle) return [];
    return Array.from(new Set(Object.values(bundle.segmento_pilar).flat()));
  }, [bundle]);

  function toggle(uuid: string) {
    const next = new Set(selected);
    if (next.has(uuid)) next.delete(uuid);
    else next.add(uuid);
    onSelectedChange(next);
  }

  function selectAll() {
    onSelectedChange(new Set([...selected, ...(campaigns ?? []).map((c) => c.uuid)]));
  }

  function deselectAll() {
    const filteredUuids = new Set((campaigns ?? []).map((c) => c.uuid));
    onSelectedChange(new Set([...selected].filter((uuid) => !filteredUuids.has(uuid))));
  }

  const selectedInView = (campaigns ?? []).filter((c) => selected.has(c.uuid)).length;

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-3 gap-3">
        <FieldSelect label="Pilar" value={pillar} options={pillarOptions} onChange={setPillar} placeholder="Todos" />
        <FieldSelect label="Medio" value={medio} options={bundle?.platforms ?? []} onChange={setMedio} placeholder="Todos" />
        <div className="flex flex-col gap-1.5">
          <label htmlFor="export-search" className="text-sm font-medium text-foreground">
            Buscar
          </label>
          <Input id="export-search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre de campaña…" />
        </div>
      </div>

      <div className="flex items-center justify-between">
        <span className="text-sm text-muted-foreground">
          {selectedInView} de {campaigns?.length ?? 0} seleccionadas
        </span>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={selectAll}>
            Seleccionar todas
          </Button>
          <Button variant="outline" size="sm" onClick={deselectAll}>
            Deseleccionar
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-1">
        {!campaigns?.length ? (
          <p className="text-sm text-muted-foreground">Sin campañas que coincidan con los filtros.</p>
        ) : (
          campaigns.map((campaign) => (
            <label
              key={campaign.uuid}
              className="flex cursor-pointer items-center gap-3 rounded-md border border-border px-3 py-2 has-checked:border-primary has-checked:bg-primary/5"
            >
              <input type="checkbox" checked={selected.has(campaign.uuid)} onChange={() => toggle(campaign.uuid)} />
              <span className="flex-1 truncate font-mono text-sm">{campaign.name}</span>
              <span className="text-xs text-muted-foreground">{campaign.meta.medio || "—"}</span>
              <span className="text-xs capitalize text-muted-foreground">{campaign.pillar_code}</span>
              <span className="text-xs text-muted-foreground">
                {campaign.ad_sets_count ?? 0} conj · {campaign.ads_count ?? 0} anun
              </span>
            </label>
          ))
        )}
      </div>
    </div>
  );
}
