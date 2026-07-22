import { useState } from "react";
import { toast } from "sonner";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { useCampaignTree } from "@/modules/core/hooks/useCampaigns";
import {
  useDeleteAd,
  useDeleteAdSet,
  useDuplicateAd,
  useDuplicateAdSet,
} from "@/modules/core/hooks/useTreeMutations";
import { AdForm } from "@/modules/builder/components/AdForm";
import { AdSetForm } from "@/modules/builder/components/AdSetForm";
import { BuilderBreadcrumb } from "@/modules/builder/components/BuilderBreadcrumb";
import { CampaignForm } from "@/modules/builder/components/CampaignForm";
import { ChildrenTable } from "@/modules/builder/components/ChildrenTable";
import { PillarTabs } from "@/modules/builder/components/PillarTabs";
import { useBuilderStore } from "@/modules/builder/states/builderStore";

/** §8.2 del SDD: Builder — drill-down Pilar ▸ Campaña ▸ Conjunto ▸ Anuncio. */
export function BuilderPage() {
  const { data: bundle, isLoading } = useDictBundle();
  const [segmento, setSegmento] = useState("");
  const { activePillar, activeCampaignUuid, activeAdSetUuid, setActivePillar, focusCampaign, focusAdSet, reset } =
    useBuilderStore();

  const { data: campaignTree } = useCampaignTree(activeCampaignUuid ?? undefined);
  const adSet = campaignTree?.ad_sets?.find((g) => g.uuid === activeAdSetUuid);

  const deleteAdSet = useDeleteAdSet();
  const duplicateAdSet = useDuplicateAdSet();
  const deleteAd = useDeleteAd();
  const duplicateAd = useDuplicateAd();

  if (isLoading || !bundle) {
    return <p className="text-sm text-muted-foreground">Cargando diccionario…</p>;
  }

  const crumbs = [{ id: "root", label: "Constructor", onClick: reset }];
  if (campaignTree) {
    crumbs.push({ id: campaignTree.uuid, label: campaignTree.name, onClick: () => focusCampaign(campaignTree.uuid) });
  }
  if (adSet) {
    crumbs.push({ id: adSet.uuid, label: adSet.name, onClick: () => focusAdSet(adSet.uuid) });
  }

  return (
    <div className="flex flex-col gap-6">
      <BuilderBreadcrumb crumbs={crumbs} />

      {!activeCampaignUuid ? (
        <>
          <PillarTabs
            bundle={bundle}
            segmento={segmento}
            pillar={activePillar ?? ""}
            onSegmentoChange={setSegmento}
            onPillarChange={setActivePillar}
          />
          {segmento && activePillar ? (
            <CampaignForm bundle={bundle} segmento={segmento} pillarCode={activePillar} onCreated={focusCampaign} />
          ) : null}
        </>
      ) : null}

      {activeCampaignUuid && campaignTree && !activeAdSetUuid ? (
        <>
          <ChildrenTable
            items={campaignTree.ad_sets ?? []}
            emptyLabel="Este pilar todavía no tiene conjuntos de anuncios."
            onOpen={(item) => focusAdSet(item.uuid)}
            onDuplicate={(item) =>
              duplicateAdSet.mutate(item.uuid, {
                onSuccess: (copy) => toast.success(`Conjunto duplicado: ${copy.name}`),
              })
            }
            onDelete={(item) =>
              deleteAdSet.mutate(item.uuid, { onSuccess: () => toast.success("Conjunto eliminado") })
            }
          />
          <AdSetForm bundle={bundle} campaignUuid={activeCampaignUuid} onCreated={focusAdSet} />
        </>
      ) : null}

      {adSet ? (
        <>
          <ChildrenTable
            items={adSet.ads ?? []}
            emptyLabel="Este conjunto todavía no tiene anuncios."
            onDuplicate={(item) =>
              duplicateAd.mutate(item.uuid, { onSuccess: (copy) => toast.success(`Anuncio duplicado: ${copy.name}`) })
            }
            onDelete={(item) => deleteAd.mutate(item.uuid, { onSuccess: () => toast.success("Anuncio eliminado") })}
          />
          <AdForm bundle={bundle} adSetUuid={adSet.uuid} onCreated={() => {}} />
        </>
      ) : null}
    </div>
  );
}
