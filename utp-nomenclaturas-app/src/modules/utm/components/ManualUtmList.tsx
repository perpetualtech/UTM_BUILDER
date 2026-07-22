import { useMemo, useState } from "react";
import { toast } from "sonner";
import { Button } from "@/modules/core/components/design-system/button";
import { Input } from "@/modules/core/components/design-system/input";
import { FieldSelect } from "@/modules/core/components/FieldSelect";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/modules/core/components/design-system/table";
import { ApiError } from "@/modules/core/types/api";
import { useDeleteManualUtm, useManualUtms } from "@/modules/utm/hooks/useManualUtm";

function copyText(text: string) {
  navigator.clipboard?.writeText(text).then(() => toast.success("Copiado"));
}

/** §3.4/§2.1 del SDD: lista de UTMs manuales guardadas — equivalente a renderManualList() del HTML. */
export function ManualUtmList() {
  const { data: utms, isLoading } = useManualUtms();
  const deleteManualUtm = useDeleteManualUtm();
  const [search, setSearch] = useState("");
  const [source, setSource] = useState("");

  const sourceOptions = useMemo(() => Array.from(new Set((utms ?? []).map((u) => u.utm_source))), [utms]);

  const filtered = useMemo(() => {
    if (!utms) return [];
    const q = search.toLowerCase();
    return utms.filter(
      (u) => (!source || u.utm_source === source) && (!q || u.url.toLowerCase().includes(q) || u.utm_campaign.toLowerCase().includes(q)),
    );
  }, [utms, search, source]);

  function handleDelete(uuid: string) {
    deleteManualUtm.mutate(uuid, {
      onSuccess: () => toast.success("Eliminada"),
      onError: (error) => toast.error(error instanceof ApiError ? error.body.error : "No se pudo eliminar."),
    });
  }

  if (isLoading) {
    return <p className="text-sm text-muted-foreground">Cargando…</p>;
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-3">
        <span className="text-sm text-muted-foreground">
          {filtered.length} de {utms?.length ?? 0} URLs
        </span>
        <div className="flex gap-2">
          <Input placeholder="Buscar…" value={search} onChange={(e) => setSearch(e.target.value)} className="w-56" />
          <FieldSelect label="Source" value={source} options={sourceOptions} onChange={setSource} placeholder="Todos los source" />
        </div>
      </div>

      {!filtered.length ? (
        <p className="text-sm text-muted-foreground">Sin UTMs manuales. Arma una arriba y guárdala.</p>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>source / medium</TableHead>
              <TableHead>Campaña</TableHead>
              <TableHead>URL final</TableHead>
              <TableHead />
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.map((utm) => (
              <TableRow key={utm.uuid}>
                <TableCell className="text-xs">
                  <span className="font-mono">{utm.utm_source}</span> / <span className="font-mono">{utm.utm_medium}</span>
                </TableCell>
                <TableCell className="font-mono text-xs">{utm.utm_campaign || "—"}</TableCell>
                <TableCell className="max-w-xs truncate font-mono text-xs">{utm.url}</TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon-sm" aria-label="Copiar URL" title="Copiar URL" onClick={() => copyText(utm.url)}>
                      ⧉
                    </Button>
                    <Button variant="ghost" size="icon-sm" aria-label="Eliminar" title="Eliminar" onClick={() => handleDelete(utm.uuid)}>
                      🗑
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  );
}
