import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import { useCreateAd } from "@/modules/core/hooks/useTreeMutations";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { LivePreview } from "@/modules/builder/components/LivePreview";
import { useAdNamePreview } from "@/modules/builder/hooks/useNamePreview";

interface AdFormProps {
  bundle: DictBundle;
  adSetUuid: string;
  onCreated: (uuid: string) => void;
}

/** §8.2 del SDD: Builder → AdForm (drill-down nivel 3, hoja del árbol). */
export function AdForm({ bundle, adSetUuid, onCreated }: AdFormProps) {
  const [formato, setFormato] = useState("");
  const [concepto, setConcepto] = useState("");
  const [motivo, setMotivo] = useState("");
  const [mensaje, setMensaje] = useState("");
  const [carrera, setCarrera] = useState("");
  const [fecha, setFecha] = useState("");
  const [url, setUrl] = useState("");

  const previewName = useAdNamePreview({ formato, concepto, motivo, mensaje, carrera, fecha });
  const createAd = useCreateAd(adSetUuid);

  function handleSubmit() {
    createAd.mutate(
      { meta: { formato, concepto, motivo, mensaje, carrera, fecha }, url: url || undefined },
      {
        onSuccess: (ad) => {
          toast.success(`Anuncio creado: ${ad.name}`);
          onCreated(ad.uuid);
        },
        onError: (error) => {
          toast.error(error instanceof ApiError ? error.body.error : "No se pudo crear el anuncio.");
        },
      },
    );
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
      <div className="grid grid-cols-2 gap-4">
        <FieldSelect label="Formato" value={formato} options={bundle.lists.formato} onChange={setFormato} />
        <FieldSelect label="Nombre (concepto)" value={concepto} options={bundle.lists.nombre} onChange={setConcepto} />
        <FieldSelect label="Motivo" value={motivo} options={bundle.lists.motivo} onChange={setMotivo} />
        <FieldSelect label="Carrera" value={carrera} options={bundle.lists.carrera} onChange={setCarrera} />
        <FieldSelect label="Fecha" value={fecha} options={bundle.lists.fecha} onChange={setFecha} />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="ad-mensaje" className="text-sm font-medium text-foreground">
          Mensaje (opcional, admite texto libre)
        </label>
        <Input id="ad-mensaje" list="mensaje-options" value={mensaje} onChange={(e) => setMensaje(e.target.value)} />
        <datalist id="mensaje-options">
          {bundle.lists.mensaje.map((option) => (
            <option key={option} value={option} />
          ))}
        </datalist>
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="ad-url" className="text-sm font-medium text-foreground">
          URL de destino (no forma parte del nombre)
        </label>
        <Input id="ad-url" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://…" />
      </div>

      <LivePreview name={previewName} />

      <div>
        <Button onClick={handleSubmit} disabled={createAd.isPending}>
          Crear anuncio
        </Button>
      </div>
    </div>
  );
}
