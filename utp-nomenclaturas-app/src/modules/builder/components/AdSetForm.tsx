import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import { useCreateAdSet } from "@/modules/core/hooks/useTreeMutations";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { LivePreview } from "@/modules/builder/components/LivePreview";
import { useAdSetDependentOptions } from "@/modules/builder/hooks/useDependentOptions";
import { useAdSetNamePreview } from "@/modules/builder/hooks/useNamePreview";

interface AdSetFormProps {
  bundle: DictBundle;
  campaignUuid: string;
  onCreated: (uuid: string) => void;
}

/** §8.2 del SDD: Builder → AdSetForm (drill-down nivel 2). */
export function AdSetForm({ bundle, campaignUuid, onCreated }: AdSetFormProps) {
  const [edad, setEdad] = useState("");
  const [ubicacion, setUbicacion] = useState("");
  const [facultad, setFacultad] = useState("");
  const [senal, setSenal] = useState("");
  const [detalle, setDetalle] = useState("");

  const { facultadOptions } = useAdSetDependentOptions(bundle, { ubicacion });
  const previewName = useAdSetNamePreview({ edad, ubicacion, facultad, senal, detalle });
  const createAdSet = useCreateAdSet(campaignUuid);

  function handleUbicacionChange(value: string) {
    setUbicacion(value);
    // D3: si la facultad elegida deja de ser válida para la nueva ubicación, se limpia.
    setFacultad("");
  }

  function handleSubmit() {
    createAdSet.mutate(
      { meta: { edad, ubicacion, facultad, senal, detalle } },
      {
        onSuccess: (adSet) => {
          toast.success(`Conjunto creado: ${adSet.name}`);
          onCreated(adSet.uuid);
        },
        onError: (error) => {
          toast.error(error instanceof ApiError ? error.body.error : "No se pudo crear el conjunto.");
        },
      },
    );
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
      <div className="grid grid-cols-2 gap-4">
        <FieldSelect label="Edad" value={edad} options={bundle.lists.edad} onChange={setEdad} />
        <FieldSelect
          label="Ubicación"
          value={ubicacion}
          options={bundle.lists.ubicacion}
          onChange={handleUbicacionChange}
        />
        <FieldSelect
          label="Facultad"
          value={facultad}
          options={facultadOptions}
          onChange={setFacultad}
          hint={
            ubicacion && facultad && !facultadOptions.includes(facultad)
              ? "Esta facultad no está disponible en la ubicación elegida"
              : undefined
          }
        />
        <FieldSelect label="Señal" value={senal} options={bundle.lists.senal} onChange={setSenal} />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="adset-detalle" className="text-sm font-medium text-foreground">
          Detalle (opcional, admite texto libre)
        </label>
        <Input id="adset-detalle" list="detalle-options" value={detalle} onChange={(e) => setDetalle(e.target.value)} />
        <datalist id="detalle-options">
          {bundle.lists.detalle.map((option) => (
            <option key={option} value={option} />
          ))}
        </datalist>
      </div>

      <LivePreview name={previewName} />

      <div>
        <Button onClick={handleSubmit} disabled={createAdSet.isPending}>
          Crear conjunto
        </Button>
      </div>
    </div>
  );
}
