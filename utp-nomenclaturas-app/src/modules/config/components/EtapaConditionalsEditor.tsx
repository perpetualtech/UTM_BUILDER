import { useState } from "react";
import { toast } from "sonner";
import { Input } from "@/modules/core/components/design-system/input";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { useUpdateEtapaOptions } from "@/modules/config/hooks/useConfigMutations";

interface EtapaConditionalsEditorProps {
  bundle: DictBundle;
}

const CONDITIONED_FIELDS: Array<{ key: "medio" | "objCamp" | "objPlat" | "tipoCamp"; label: string }> = [
  { key: "medio", label: "Medios disponibles" },
  { key: "objCamp", label: "Obj. Campaña" },
  { key: "objPlat", label: "Obj. Plataforma" },
  { key: "tipoCamp", label: "Tipo de Campaña" },
];

function reportError(action: string) {
  return (error: unknown) => toast.error(error instanceof ApiError ? error.body.error : `No se pudo ${action}.`);
}

/**
 * D1 (§3.2, Config Nivel 1): una tarjeta por etapa con sus 4 campos
 * condicionados editables — puerto de `renderCfgNivel1()` (tarjetas de
 * etapa) del HTML de referencia. Cada chip add/remove hace PUT del array
 * completo (§7.1: "Reemplaza opciones condicionadas por etapa").
 */
export function EtapaConditionalsEditor({ bundle }: EtapaConditionalsEditorProps) {
  return (
    <div className="grid grid-cols-3 gap-4">
      {bundle.lists.etapa.map((etapa) => (
        <EtapaCard key={etapa} etapa={etapa} bundle={bundle} />
      ))}
    </div>
  );
}

function EtapaCard({ etapa, bundle }: { etapa: string; bundle: DictBundle }) {
  return (
    <div className="rounded-lg border border-border bg-card">
      <div className="border-b border-border px-4 py-3">
        <h3 className="font-mono text-sm font-semibold uppercase tracking-wide text-foreground">{etapa}</h3>
        <p className="text-xs text-muted-foreground">Condicionales para la etapa {etapa}</p>
      </div>
      <div className="flex flex-col gap-4 p-4">
        {CONDITIONED_FIELDS.map(({ key, label }) => (
          <EtapaFieldEditor key={key} etapa={etapa} field={key} label={label} values={bundle.etapa_conditionals[key][etapa] ?? []} />
        ))}
      </div>
    </div>
  );
}

function EtapaFieldEditor({
  etapa,
  field,
  label,
  values,
}: {
  etapa: string;
  field: "medio" | "objCamp" | "objPlat" | "tipoCamp";
  label: string;
  values: string[];
}) {
  const [newValue, setNewValue] = useState("");
  const updateEtapaOptions = useUpdateEtapaOptions();
  const inputId = `etapa-add-${field}-${etapa}`;

  function commit(next: string[], successMessage: string) {
    updateEtapaOptions.mutate({ etapa, field, values: next }, { onSuccess: () => toast.success(successMessage), onError: reportError("actualizar la condicional") });
  }

  function handleAdd() {
    const value = newValue.trim();
    if (!value) {
      toast.error("Escribe un valor");
      return;
    }
    if (values.includes(value)) {
      toast.error("Ya existe en esta etapa");
      return;
    }
    commit([...values, value], "Agregado");
    setNewValue("");
  }

  return (
    <div>
      <p className="mb-1 text-[10.5px] font-bold uppercase tracking-wide text-muted-foreground">{label}</p>
      <div className="flex flex-wrap gap-1.5 rounded-md border border-border p-2">
        {values.length === 0 ? (
          <span className="text-xs italic text-muted-foreground">Sin valores</span>
        ) : (
          values.map((value) => (
            <span key={value} className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs">
              {value}
              <button
                type="button"
                aria-label={`Eliminar ${value}`}
                className="text-muted-foreground hover:text-destructive"
                onClick={() => commit(values.filter((v) => v !== value), "Eliminado")}
              >
                ×
              </button>
            </span>
          ))
        )}
      </div>
      <div className="mt-1.5 flex gap-1.5">
        <label htmlFor={inputId} className="sr-only">
          Agregar valor a {label} ({etapa})
        </label>
        <Input
          id={inputId}
          value={newValue}
          onChange={(e) => setNewValue(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && handleAdd()}
          placeholder="Agregar…"
          className="h-7 text-xs"
        />
      </div>
    </div>
  );
}
