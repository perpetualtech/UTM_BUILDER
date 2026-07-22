import { useState } from "react";
import { toast } from "sonner";
import { Badge } from "@/modules/core/components/design-system/badge";
import { Button } from "@/modules/core/components/design-system/button";
import { EditableRow } from "@/modules/core/components/EditableRow";
import { useCampaignTree } from "@/modules/core/hooks/useCampaigns";
import {
  useDeleteAd,
  useDeleteAdSet,
  useDeleteCampaign,
  useDuplicateAd,
  useDuplicateAdSet,
  useDuplicateCampaign,
  useUpdateAd,
  useUpdateAdSet,
  useUpdateCampaign,
} from "@/modules/core/hooks/useTreeMutations";
import { ApiError } from "@/modules/core/types/api";
import type { Ad, AdSet, Campaign } from "@/modules/core/types/api";

interface CampaignRowProps {
  campaign: Campaign;
}

function reportError(action: string) {
  return (error: unknown) => {
    toast.error(error instanceof ApiError ? error.body.error : `No se pudo ${action}.`);
  };
}

/**
 * §8.2 del SDD: Repository → árbol filtrable + edición inline. Renombrar
 * acá es un override manual del string (igual que saveEdit() en el HTML de
 * referencia) — no re-deriva desde los campos meta. Duplicar/eliminar
 * replican dupCamp()/delCamp() (y equivalentes de conjunto/anuncio) del
 * HTML de referencia, incluida la confirmación antes de eliminar.
 */
export function CampaignRow({ campaign }: CampaignRowProps) {
  const [expanded, setExpanded] = useState(false);
  const { data: tree } = useCampaignTree(expanded ? campaign.uuid : undefined);
  const updateCampaign = useUpdateCampaign(campaign.uuid);
  const duplicateCampaign = useDuplicateCampaign();
  const deleteCampaign = useDeleteCampaign();

  function renameCampaign(name: string) {
    updateCampaign.mutate({ name }, { onError: reportError("renombrar") });
  }

  function handleDuplicate() {
    duplicateCampaign.mutate(campaign.uuid, {
      onSuccess: (copy) => toast.success(`Campaña duplicada: ${copy.name}`),
      onError: reportError("duplicar"),
    });
  }

  function handleDelete() {
    if (!window.confirm("¿Eliminar esta campaña y todos sus conjuntos y anuncios?")) return;
    deleteCampaign.mutate(campaign.uuid, {
      onSuccess: () => toast.success("Campaña eliminada"),
      onError: reportError("eliminar"),
    });
  }

  return (
    <div className="rounded-lg border border-border bg-card">
      <div className="flex items-center gap-3 px-3 py-2">
        <Button variant="ghost" size="sm" onClick={() => setExpanded((v) => !v)}>
          {expanded ? "▾" : "▸"}
        </Button>
        <Badge variant="outline">{campaign.pillar_code}</Badge>
        {campaign.meta.medio ? <Badge>{campaign.meta.medio}</Badge> : null}
        <EditableRow value={campaign.name} onSave={renameCampaign} className="flex-1" />
        <span className="text-xs text-muted-foreground">
          {campaign.ad_sets_count ?? 0} conjuntos · {campaign.ads_count ?? 0} anuncios
        </span>
        <Button variant="outline" size="sm" onClick={handleDuplicate}>
          Duplicar
        </Button>
        <Button variant="destructive" size="sm" onClick={handleDelete}>
          Eliminar
        </Button>
      </div>

      {expanded && tree?.ad_sets ? (
        <div className="flex flex-col gap-2 border-t border-border px-3 py-2 pl-10">
          {tree.ad_sets.length === 0 ? (
            <p className="text-xs text-muted-foreground">Sin conjuntos todavía.</p>
          ) : (
            tree.ad_sets.map((adSet) => <AdSetRow key={adSet.uuid} adSet={adSet} />)
          )}
        </div>
      ) : null}
    </div>
  );
}

function AdSetRow({ adSet }: { adSet: AdSet }) {
  const updateAdSet = useUpdateAdSet(adSet.uuid);
  const duplicateAdSet = useDuplicateAdSet();
  const deleteAdSet = useDeleteAdSet();

  function renameAdSet(name: string) {
    updateAdSet.mutate({ name }, { onError: reportError("renombrar") });
  }

  function handleDuplicate() {
    duplicateAdSet.mutate(adSet.uuid, {
      onSuccess: (copy) => toast.success(`Conjunto duplicado: ${copy.name}`),
      onError: reportError("duplicar"),
    });
  }

  function handleDelete() {
    if (!window.confirm("¿Eliminar este conjunto y todos sus anuncios?")) return;
    deleteAdSet.mutate(adSet.uuid, {
      onSuccess: () => toast.success("Conjunto eliminado"),
      onError: reportError("eliminar"),
    });
  }

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-2">
        <EditableRow value={adSet.name} onSave={renameAdSet} className="flex-1" />
        <Button variant="outline" size="sm" onClick={handleDuplicate}>
          Duplicar
        </Button>
        <Button variant="destructive" size="sm" onClick={handleDelete}>
          Eliminar
        </Button>
      </div>
      {adSet.ads?.length ? (
        <div className="flex flex-col gap-1 pl-6">
          {adSet.ads.map((ad) => (
            <AdRow key={ad.uuid} ad={ad} />
          ))}
        </div>
      ) : null}
    </div>
  );
}

function AdRow({ ad }: { ad: Ad }) {
  const updateAd = useUpdateAd(ad.uuid);
  const duplicateAd = useDuplicateAd();
  const deleteAd = useDeleteAd();

  function renameAd(name: string) {
    updateAd.mutate({ name }, { onError: reportError("renombrar") });
  }

  function handleDuplicate() {
    duplicateAd.mutate(ad.uuid, {
      onSuccess: (copy) => toast.success(`Anuncio duplicado: ${copy.name}`),
      onError: reportError("duplicar"),
    });
  }

  function handleDelete() {
    if (!window.confirm("¿Eliminar este anuncio?")) return;
    deleteAd.mutate(ad.uuid, {
      onSuccess: () => toast.success("Anuncio eliminado"),
      onError: reportError("eliminar"),
    });
  }

  return (
    <div className="flex items-center gap-2">
      <EditableRow value={ad.name} onSave={renameAd} className="flex-1" />
      <Button variant="outline" size="sm" onClick={handleDuplicate}>
        Duplicar
      </Button>
      <Button variant="destructive" size="sm" onClick={handleDelete}>
        Eliminar
      </Button>
    </div>
  );
}
