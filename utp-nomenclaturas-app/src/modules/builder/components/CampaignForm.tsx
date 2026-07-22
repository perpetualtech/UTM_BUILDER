import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import { useCreateCampaign } from "@/modules/core/hooks/useTreeMutations";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { LivePreview } from "@/modules/builder/components/LivePreview";
import { useCampaignDependentOptions } from "@/modules/builder/hooks/useDependentOptions";
import { useCampaignNamePreview } from "@/modules/builder/hooks/useNamePreview";

interface CampaignFormProps {
  bundle: DictBundle;
  segmento: string;
  pillarCode: string;
  onCreated: (uuid: string) => void;
}

interface CampaignFormFields {
  etapa: string;
  campus: string;
  medio: string;
  objCamp: string;
  objPlat: string;
  tipoCamp: string;
}

const EMPTY_FIELDS: CampaignFormFields = {
  etapa: "",
  campus: "",
  medio: "",
  objCamp: "",
  objPlat: "",
  tipoCamp: "",
};

/** §8.2 del SDD: Builder → CampaignForm (drill-down nivel 1). */
export function CampaignForm({ bundle, segmento, pillarCode, onCreated }: CampaignFormProps) {
  // Un solo estado para los 6 campos: cambiar etapa resetea 4 campos
  // dependientes (D1, §3.2) en una sola actualización atómica.
  const [fields, setFields] = useState<CampaignFormFields>(EMPTY_FIELDS);

  const { medioOptions, objCampOptions, objPlatOptions, tipoCampOptions } = useCampaignDependentOptions(bundle, {
    segmento,
    etapa: fields.etapa,
  });

  const previewName = useCampaignNamePreview({
    segmento,
    etapa: fields.etapa,
    campus: fields.campus,
    medio: fields.medio,
    obj_camp: fields.objCamp,
    obj_plat: fields.objPlat,
    tipo_camp: fields.tipoCamp,
    pillar_code: pillarCode,
  });

  const createCampaign = useCreateCampaign();

  function handleEtapaChange(etapa: string) {
    // D1: al cambiar etapa, los campos dependientes se limpian (§3.2 del SDD).
    setFields({ ...EMPTY_FIELDS, etapa });
  }

  function setField<K extends keyof CampaignFormFields>(key: K) {
    return (value: string) => setFields((prev) => ({ ...prev, [key]: value }));
  }

  function handleSubmit() {
    createCampaign.mutate(
      {
        pillar_code: pillarCode,
        meta: {
          segmento,
          etapa: fields.etapa,
          campus: fields.campus,
          medio: fields.medio,
          obj_camp: fields.objCamp,
          obj_plat: fields.objPlat,
          tipo_camp: fields.tipoCamp,
        },
      },
      {
        onSuccess: (campaign) => {
          toast.success(`Campaña creada: ${campaign.name}`);
          onCreated(campaign.uuid);
        },
        onError: (error) => {
          toast.error(error instanceof ApiError ? error.body.error : "No se pudo crear la campaña.");
        },
      },
    );
  }

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4">
      <div className="grid grid-cols-2 gap-4">
        <FieldSelect label="Etapa" value={fields.etapa} options={bundle.lists.etapa} onChange={handleEtapaChange} />
        <FieldSelect label="Campus" value={fields.campus} options={bundle.lists.campus} onChange={setField("campus")} />
        <FieldSelect
          label="Medio"
          value={fields.medio}
          options={medioOptions}
          onChange={setField("medio")}
          disabled={!fields.etapa}
          hint={!fields.etapa ? "Selecciona una etapa primero" : undefined}
        />
        <FieldSelect
          label="Objetivo de campaña"
          value={fields.objCamp}
          options={objCampOptions}
          onChange={setField("objCamp")}
          disabled={!fields.etapa}
        />
        <FieldSelect
          label="Objetivo de plataforma"
          value={fields.objPlat}
          options={objPlatOptions}
          onChange={setField("objPlat")}
          disabled={!fields.etapa}
        />
        <FieldSelect
          label="Tipo de campaña"
          value={fields.tipoCamp}
          options={tipoCampOptions}
          onChange={setField("tipoCamp")}
          disabled={!fields.etapa}
        />
      </div>

      <LivePreview name={previewName} />

      <div>
        <Button onClick={handleSubmit} disabled={createCampaign.isPending}>
          Crear campaña
        </Button>
      </div>
    </div>
  );
}
