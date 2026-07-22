import { useRef, useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { ApiError } from "@/modules/core/types/api";
import { CampaignPicker } from "@/modules/export/components/CampaignPicker";
import {
  useExportBackup,
  useExportCampaignsExcel,
  useExportUtmsExcel,
  useImportBackup,
} from "@/modules/export/hooks/useExport";

function reportError(action: string) {
  return (error: unknown) => toast.error(error instanceof ApiError ? error.body.error : `No se pudo ${action}.`);
}

/** §3.5/§10 del SDD: Export — Excel de nomenclaturas/UTMs + backup/restore JSON. */
export function ExportPage() {
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const exportCampaigns = useExportCampaignsExcel();
  const exportUtms = useExportUtmsExcel();
  const exportBackup = useExportBackup();
  const importBackup = useImportBackup();
  const fileInputRef = useRef<HTMLInputElement>(null);

  function handleExportCampaigns() {
    exportCampaigns.mutate(
      { uuids: selected.size ? Array.from(selected) : undefined },
      { onSuccess: () => toast.success("Excel generado"), onError: reportError("generar el Excel") },
    );
  }

  function handleExportUtms() {
    exportUtms.mutate(undefined, { onSuccess: () => toast.success("Excel de UTMs generado"), onError: reportError("generar el Excel de UTMs") });
  }

  function handleExportBackup() {
    exportBackup.mutate(undefined, { onSuccess: () => toast.success("Backup descargado"), onError: reportError("descargar el backup") });
  }

  function handleImportFile(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
      try {
        const data = JSON.parse(String(reader.result));
        importBackup.mutate(data, {
          onSuccess: (summary) =>
            toast.success(`Restaurado: ${summary.created} creadas, ${summary.updated} actualizadas, ${summary.skipped} omitidas`),
          onError: reportError("restaurar el backup"),
        });
      } catch {
        toast.error("JSON inválido");
      }
    };
    reader.readAsText(file);
  }

  return (
    <div className="flex flex-col gap-6">
      <section className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-foreground">Excel de nomenclaturas</h2>
        <CampaignPicker selected={selected} onSelectedChange={setSelected} />
        <div>
          <Button onClick={handleExportCampaigns} disabled={exportCampaigns.isPending}>
            Exportar Excel
          </Button>
        </div>
      </section>

      <section className="flex flex-col gap-3 border-t border-border pt-4">
        <h2 className="text-sm font-semibold text-foreground">Excel de UTMs</h2>
        <p className="text-sm text-muted-foreground">Incluye todas las UTMs derivadas (paid) y manuales, una hoja por source + Consolidado.</p>
        <div>
          <Button onClick={handleExportUtms} disabled={exportUtms.isPending}>
            Exportar Excel de UTMs
          </Button>
        </div>
      </section>

      <section className="flex flex-col gap-3 border-t border-border pt-4">
        <h2 className="text-sm font-semibold text-foreground">Backup / restauración</h2>
        <div className="flex gap-2">
          <Button variant="outline" onClick={handleExportBackup} disabled={exportBackup.isPending}>
            Descargar backup JSON
          </Button>
          <Button variant="outline" onClick={() => fileInputRef.current?.click()} disabled={importBackup.isPending}>
            Restaurar backup
          </Button>
          <input ref={fileInputRef} type="file" accept="application/json" className="hidden" onChange={handleImportFile} />
        </div>
      </section>
    </div>
  );
}
