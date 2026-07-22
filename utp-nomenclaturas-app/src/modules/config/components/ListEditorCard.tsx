import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { ApiError } from "@/modules/core/types/api";
import { useAddListValue, useDeleteListValue } from "@/modules/config/hooks/useConfigMutations";

interface ListEditorCardProps {
  listKey: string;
  label: string;
  values: string[];
}

function reportError(action: string) {
  return (error: unknown) => toast.error(error instanceof ApiError ? error.body.error : `No se pudo ${action}.`);
}

/**
 * Lista plana editable (Config Nivel 1/3, §7.1 del SDD) — puerto de
 * `listEditorCard()` del HTML de referencia: agregar/quitar valores de a
 * uno, sin edición in-place (el HTML nunca lo permitió).
 */
export function ListEditorCard({ listKey, label, values }: ListEditorCardProps) {
  const [newValue, setNewValue] = useState("");
  const addValue = useAddListValue();
  const deleteValue = useDeleteListValue();
  const inputId = `list-editor-add-${listKey}`;

  function handleAdd() {
    const value = newValue.trim();
    if (!value) {
      toast.error("Escribe un valor");
      return;
    }
    addValue.mutate(
      { listKey, value },
      {
        onSuccess: () => {
          setNewValue("");
          toast.success("Agregado");
        },
        onError: reportError("agregar el valor"),
      },
    );
  }

  function handleRemove(value: string) {
    deleteValue.mutate({ listKey, value }, { onSuccess: () => toast.success("Eliminado"), onError: reportError("eliminar el valor") });
  }

  return (
    <div className="rounded-lg border border-border bg-card">
      <div className="border-b border-border px-4 py-3">
        <h3 className="text-sm font-semibold text-foreground">{label}</h3>
        <p className="text-xs text-muted-foreground">
          Lista editable · <code>{listKey}</code> · {values.length} valores
        </p>
      </div>
      <div className="flex flex-col gap-3 p-4">
        <div className="flex flex-wrap gap-2">
          {values.length === 0 ? (
            <span className="text-xs italic text-muted-foreground">Sin valores</span>
          ) : (
            values.map((value) => (
              <span key={value} className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2.5 py-1 text-xs">
                {value}
                <button
                  type="button"
                  aria-label={`Eliminar ${value}`}
                  className="text-muted-foreground hover:text-destructive"
                  onClick={() => handleRemove(value)}
                >
                  ×
                </button>
              </span>
            ))
          )}
        </div>
        <div className="flex gap-2">
          <label htmlFor={inputId} className="sr-only">
            Nuevo valor para {label}
          </label>
          <Input
            id={inputId}
            value={newValue}
            onChange={(e) => setNewValue(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && handleAdd()}
            placeholder="Nuevo valor…"
          />
          <Button type="button" variant="outline" size="sm" onClick={handleAdd} disabled={addValue.isPending}>
            Agregar
          </Button>
        </div>
      </div>
    </div>
  );
}
