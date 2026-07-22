import { useMemo, useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { clean } from "@/modules/core/lib/utmClean";
import { joinUrl } from "@/modules/core/lib/utmDeriver";
import { ApiError } from "@/modules/core/types/api";
import { useCreateManualUtm } from "@/modules/utm/hooks/useManualUtm";

interface ManualFields {
  url: string;
  source: string;
  medium: string;
  campaign: string;
  term: string;
  content: string;
}

const EMPTY_FIELDS: ManualFields = { url: "", source: "", medium: "", campaign: "", term: "", content: "" };

function labeledInput(id: string, label: string, value: string, onChange: (v: string) => void, list?: string) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-foreground">
        {label}
      </label>
      <Input id={id} value={value} onChange={(e) => onChange(e.target.value)} list={list} />
    </div>
  );
}

/** §3.4/§2.1 del SDD: builder de UTM manual (tráfico sin pauta) — puerto de buildManual()/saveManual() del HTML. */
export function ManualUtmBuilder() {
  const { data: bundle } = useDictBundle();
  const [fields, setFields] = useState<ManualFields>(EMPTY_FIELDS);
  const [cleanEnabled, setCleanEnabled] = useState(true);
  const createManualUtm = useCreateManualUtm();

  function setField<K extends keyof ManualFields>(key: K) {
    return (value: string) => setFields((prev) => ({ ...prev, [key]: value }));
  }

  const cleaned = useMemo(
    () => ({
      source: clean(fields.source, cleanEnabled),
      medium: clean(fields.medium, cleanEnabled),
      campaign: clean(fields.campaign, cleanEnabled),
      term: clean(fields.term, cleanEnabled),
      content: clean(fields.content, cleanEnabled),
    }),
    [fields, cleanEnabled],
  );

  const parts = useMemo(() => {
    const entries: Array<[string, string]> = [];
    if (cleaned.source) entries.push(["utm_source", cleaned.source]);
    if (cleaned.medium) entries.push(["utm_medium", cleaned.medium]);
    if (cleaned.campaign) entries.push(["utm_campaign", cleaned.campaign]);
    if (cleaned.term) entries.push(["utm_term", cleaned.term]);
    if (cleaned.content) entries.push(["utm_content", cleaned.content]);
    return entries;
  }, [cleaned]);

  const qs = parts.map(([key, value]) => `${key}=${encodeURIComponent(value)}`).join("&");
  const url = fields.url.trim();
  const full = joinUrl(url, qs);

  const warnings: string[] = [];
  if (cleaned.source && bundle?.reserved_src.includes(cleaned.source)) {
    warnings.push(`El source "${cleaned.source}" fragmenta en GA4. Usa instagram, tiktok, youtube, facebook, google…`);
  }
  if (!cleaned.source || !cleaned.medium) {
    warnings.push("source y medium son obligatorios para que GA4 clasifique el canal.");
  }

  function handleSave() {
    if (!parts.length) {
      toast.error("Completa los campos");
      return;
    }
    if (!cleaned.source || !cleaned.medium) {
      toast.error("source y medium son obligatorios");
      return;
    }
    if (!url) {
      toast.error("Agrega la URL de destino");
      return;
    }
    createManualUtm.mutate(
      { utm_source: cleaned.source, utm_medium: cleaned.medium, utm_campaign: cleaned.campaign, utm_term: cleaned.term, utm_content: cleaned.content, url: full, qs },
      {
        onSuccess: () => {
          toast.success("UTM guardada");
          setFields(EMPTY_FIELDS);
        },
        onError: (error) => toast.error(error instanceof ApiError ? error.body.error : "No se pudo guardar la UTM."),
      },
    );
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
      <div className="grid grid-cols-2 gap-4">
        {labeledInput("ux-url", "URL de destino", fields.url, setField("url"))}
        {labeledInput("ux-source", "Source", fields.source, setField("source"), "ux-source-list")}
        {labeledInput("ux-medium", "Medium", fields.medium, setField("medium"), "ux-medium-list")}
        {labeledInput("ux-campaign", "Campaign", fields.campaign, setField("campaign"))}
        {labeledInput("ux-term", "Term", fields.term, setField("term"))}
        {labeledInput("ux-content", "Content", fields.content, setField("content"))}
      </div>

      <datalist id="ux-source-list">
        {(bundle?.ux_sources ?? []).map((s) => (
          <option key={s} value={s} />
        ))}
      </datalist>
      <datalist id="ux-medium-list">
        {(bundle?.ux_mediums ?? []).map((m) => (
          <option key={m} value={m} />
        ))}
      </datalist>

      <label className="flex items-center gap-2 text-sm text-muted-foreground">
        <input type="checkbox" checked={cleanEnabled} onChange={(e) => setCleanEnabled(e.target.checked)} />
        Limpiar (minúsculas, sin acentos, espacios → guiones)
      </label>

      <div className="rounded-md border border-dashed border-border bg-muted/40 p-3">
        <p className="text-xs font-medium text-muted-foreground">String UTM ensamblado</p>
        {parts.length ? (
          <p className="font-mono text-sm break-all">
            {parts.map(([key, value]) => `${key}=${value}`).join("&")}
          </p>
        ) : (
          <p className="text-sm text-muted-foreground">Completa los campos…</p>
        )}
        {warnings.map((warning) => (
          <p key={warning} className="mt-1 text-xs font-medium text-[color:var(--color-warn)]">
            {warning}
          </p>
        ))}
        <p className="mt-2 text-xs text-muted-foreground">
          <span className="font-semibold">URL final:</span> {url ? <span className="break-all">{full}</span> : "añade la URL de destino…"}
        </p>
      </div>

      <div>
        <Button onClick={handleSave} disabled={createManualUtm.isPending}>
          Guardar UTM
        </Button>
      </div>
    </div>
  );
}
