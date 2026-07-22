import { useMemo, useState } from "react";
import { toast } from "sonner";
import { Badge } from "@/modules/core/components/design-system/badge";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import type { PaidUtmRow } from "@/modules/core/types/api";
import { MetaModeToggle } from "@/modules/utm/components/MetaModeToggle";
import { useUpdateUtmConfig, useUtmConfig } from "@/modules/utm/hooks/useUtmConfig";
import { usePaidUtms } from "@/modules/utm/hooks/useUtmPaid";

function copyText(text: string) {
  if (!text) {
    toast.error("Nada que copiar");
    return;
  }
  navigator.clipboard?.writeText(text).then(() => toast.success("Copiado"));
}

interface CampaignGroup {
  campaign_uuid: string;
  campaign_name: string;
  plat: string;
  pillar_code: string;
  adSets: Map<string, { ad_set_name: string; rows: PaidUtmRow[] }>;
}

function groupByCampaign(rows: PaidUtmRow[]): CampaignGroup[] {
  const byCampaign = new Map<string, CampaignGroup>();
  for (const row of rows) {
    let group = byCampaign.get(row.campaign_uuid);
    if (!group) {
      group = {
        campaign_uuid: row.campaign_uuid,
        campaign_name: row.campaign_name,
        plat: row.plat,
        pillar_code: row.pillar_code,
        adSets: new Map(),
      };
      byCampaign.set(row.campaign_uuid, group);
    }
    let adSet = group.adSets.get(row.ad_set_uuid);
    if (!adSet) {
      adSet = { ad_set_name: row.ad_set_name, rows: [] };
      group.adSets.set(row.ad_set_uuid, adSet);
    }
    adSet.rows.push(row);
  }
  return Array.from(byCampaign.values());
}

/** §7/§8 del SDD: vista "Paid" — equivalente a `renderPaid()` del HTML de referencia. */
export function PaidUtmTree() {
  const { data: rows, isLoading } = usePaidUtms();
  const { data: config } = useUtmConfig();
  const updateConfig = useUpdateUtmConfig();

  const [search, setSearch] = useState("");
  const [plat, setPlat] = useState("");
  const [pillar, setPillar] = useState("");

  const filtered = useMemo(() => {
    if (!rows) return [];
    const q = search.toLowerCase();
    return rows.filter(
      (r) =>
        (!plat || r.plat === plat) &&
        (!pillar || r.pillar_code === pillar) &&
        (!q || r.ad_name.toLowerCase().includes(q) || r.ad_set_name.toLowerCase().includes(q) || r.campaign_name.toLowerCase().includes(q)),
    );
  }, [rows, search, plat, pillar]);

  const groups = useMemo(() => groupByCampaign(filtered), [filtered]);

  const platOptions = useMemo(() => Array.from(new Set((rows ?? []).map((r) => r.plat))), [rows]);
  const pillarOptions = useMemo(() => Array.from(new Set((rows ?? []).map((r) => r.pillar_code))), [rows]);

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">Cargando…</p>;
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        {config ? <MetaModeToggle config={config} onChange={(meta_mode) => updateConfig.mutate({ ...config, meta_mode })} /> : null}
        <span className="text-sm text-muted-foreground">{filtered.length} anuncios con UTM</span>
      </div>

      <div className="grid grid-cols-3 gap-3">
        <div className="flex flex-col gap-1.5">
          <label htmlFor="paid-utm-search" className="text-sm font-medium text-foreground">
            Buscar
          </label>
          <Input id="paid-utm-search" placeholder="Nombre de anuncio, conjunto o campaña…" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <FieldSelect label="Medio" value={plat} options={platOptions} onChange={setPlat} placeholder="Todos los medios" />
        <FieldSelect label="Pilar" value={pillar} options={pillarOptions} onChange={setPillar} placeholder="Todos los pilares" />
      </div>

      {!groups.length ? (
        <p className="text-sm text-muted-foreground">
          Aún no hay anuncios con UTM. Crea campañas ▸ conjuntos ▸ anuncios en el Constructor y coloca su URL en el Nivel 3.
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {groups.map((group) => (
            <div key={group.campaign_uuid} className="rounded-lg border border-border bg-card">
              <div className="flex items-center gap-2 border-b border-border px-3 py-2">
                <span className="font-mono text-sm font-semibold">{group.campaign_name}</span>
                <Badge>{group.plat}</Badge>
                <Badge variant="outline" className="capitalize">
                  {group.pillar_code}
                </Badge>
                <span className="text-xs text-muted-foreground">
                  {group.adSets.size} conj · {Array.from(group.adSets.values()).reduce((n, g) => n + g.rows.length, 0)} anun
                </span>
              </div>
              <div className="flex flex-col gap-2 px-3 py-2">
                {Array.from(group.adSets.entries()).map(([adSetUuid, adSet]) => (
                  <div key={adSetUuid} className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <Badge variant="outline">Conjunto</Badge>
                      <span className="font-mono">{adSet.ad_set_name}</span>
                    </div>
                    {adSet.rows.map((row) => (
                      <div key={row.ad_uuid} className="flex flex-col gap-1 border-l-2 border-border pl-3">
                        <span className="font-mono text-sm font-medium">{row.ad_name}</span>
                        <span className="font-mono text-xs break-all text-muted-foreground">{row.params}</span>
                        <div className="flex flex-wrap items-center gap-2">
                          <button
                            type="button"
                            className="text-xs text-primary underline-offset-2 hover:underline"
                            onClick={() => copyText(row.params)}
                          >
                            Copiar parámetros
                          </button>
                          {row.url ? (
                            <button
                              type="button"
                              className="text-xs text-primary underline-offset-2 hover:underline"
                              onClick={() => copyText(row.sep ? row.url : row.full)}
                            >
                              {row.sep ? "Copiar URL" : "Copiar URL + params"}
                            </button>
                          ) : (
                            <span className="text-xs font-semibold text-[color:var(--color-warn)]">Sin URL · colócala en el Nivel 3</span>
                          )}
                          <span className="text-[11px] text-muted-foreground">{row.where}</span>
                          {!row.sep ? (
                            <span className="text-[10.5px] font-bold text-primary">⚠ URL y parámetros van JUNTOS</span>
                          ) : null}
                        </div>
                      </div>
                    ))}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
