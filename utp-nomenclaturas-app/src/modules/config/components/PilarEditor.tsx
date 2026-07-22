import { useState } from "react";
import { toast } from "sonner";
import { Input } from "@/modules/core/components/design-system/input";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { useUpdateSegmentoPilar } from "@/modules/config/hooks/useConfigMutations";

interface PilarEditorProps {
  bundle: DictBundle;
}

function reportError(action: string) {
  return (error: unknown) => toast.error(error instanceof ApiError ? error.body.error : `No se pudo ${action}.`);
}

/** D2 (§3.2, Config Nivel 1): pilares disponibles por segmento — puerto de la tarjeta "Pilares Estratégicos" del HTML. */
export function PilarEditor({ bundle }: PilarEditorProps) {
  return (
    <div className="rounded-lg border border-border bg-card">
      <div className="border-b border-border px-4 py-3">
        <h3 className="text-sm font-semibold text-foreground">Pilares Estratégicos</h3>
        <p className="text-xs text-muted-foreground">Pilares disponibles por segmento</p>
      </div>
      <div className="flex flex-col gap-4 p-4">
        {(["adultos", "jovenes"] as const).map((segmento) => (
          <SegmentoPilarEditor key={segmento} segmento={segmento} pilares={bundle.segmento_pilar[segmento] ?? []} />
        ))}
      </div>
    </div>
  );
}

function SegmentoPilarEditor({ segmento, pilares }: { segmento: string; pilares: string[] }) {
  const [newValue, setNewValue] = useState("");
  const updateSegmentoPilar = useUpdateSegmentoPilar();
  const inputId = `pilar-add-${segmento}`;

  function commit(next: string[], successMessage: string) {
    updateSegmentoPilar.mutate(
      { segmento, pilares: next },
      { onSuccess: () => toast.success(successMessage), onError: reportError("actualizar los pilares") },
    );
  }

  function handleAdd() {
    const value = newValue.trim();
    if (!value) {
      toast.error("Escribe un nombre de pilar");
      return;
    }
    if (pilares.includes(value)) {
      toast.error("Ya existe en este segmento");
      return;
    }
    commit([...pilares, value], "Pilar agregado");
    setNewValue("");
  }

  return (
    <div>
      <p className="mb-1 text-[10.5px] font-bold uppercase tracking-wide text-muted-foreground">{segmento}</p>
      <div className="flex flex-wrap gap-1.5 rounded-md border border-border p-2">
        {pilares.length === 0 ? (
          <span className="text-xs italic text-muted-foreground">Sin pilares</span>
        ) : (
          pilares.map((pilar) => (
            <span key={pilar} className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs capitalize">
              {pilar}
              <button
                type="button"
                aria-label={`Eliminar pilar ${pilar}`}
                className="text-muted-foreground hover:text-destructive"
                onClick={() => commit(pilares.filter((p) => p !== pilar), "Pilar eliminado")}
              >
                ×
              </button>
            </span>
          ))
        )}
      </div>
      <div className="mt-1.5">
        <label htmlFor={inputId} className="sr-only">
          Nombre del pilar ({segmento})
        </label>
        <Input
          id={inputId}
          value={newValue}
          onChange={(e) => setNewValue(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && handleAdd()}
          placeholder="Nombre del pilar…"
          className="h-7 text-xs"
        />
      </div>
    </div>
  );
}
