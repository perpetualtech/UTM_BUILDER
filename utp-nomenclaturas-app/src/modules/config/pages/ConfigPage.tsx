import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/modules/core/components/design-system/tabs";
import { useDictBundle } from "@/modules/core/hooks/useDictBundle";
import { CampusFacultadMatrix } from "@/modules/config/components/CampusFacultadMatrix";
import { EtapaConditionalsEditor } from "@/modules/config/components/EtapaConditionalsEditor";
import { ListEditorCard } from "@/modules/config/components/ListEditorCard";
import { PilarEditor } from "@/modules/config/components/PilarEditor";

const NIVEL3_LISTS: Array<{ key: "formato" | "nombre" | "motivo" | "mensaje" | "carrera" | "fecha"; label: string }> = [
  { key: "formato", label: "Formato" },
  { key: "nombre", label: "Nombre Creativo" },
  { key: "motivo", label: "Motivo" },
  { key: "mensaje", label: "Mensaje Clave" },
  { key: "carrera", label: "Carrera" },
  { key: "fecha", label: "Fecha" },
];

/**
 * §7.1/§11 Fase 4 del SDD: Config Nivel 1 (Campaña) / Nivel 2 (Conjunto,
 * incl. matriz D4) / Nivel 3 (Anuncio) — puerto de `renderCfgNivel1/2/3()`
 * del HTML de referencia. Protegido server-side por el permiso
 * `administer utp nomenclaturas config` (403 si falta, igual que
 * cualquier otra mutación de esta API — sin guard de ruta en el cliente).
 */
export function ConfigPage() {
  const { data: bundle, isLoading } = useDictBundle();

  if (isLoading || !bundle) {
    return <p className="text-sm text-muted-foreground">Cargando diccionario…</p>;
  }

  return (
    <Tabs defaultValue="nivel1" className="flex flex-col gap-4">
      <TabsList>
        <TabsTrigger value="nivel1">Nivel 1 · Campaña</TabsTrigger>
        <TabsTrigger value="nivel2">Nivel 2 · Conjunto</TabsTrigger>
        <TabsTrigger value="nivel3">Nivel 3 · Anuncio</TabsTrigger>
      </TabsList>

      <TabsContent value="nivel1" className="flex flex-col gap-4">
        <EtapaConditionalsEditor bundle={bundle} />
        <div className="grid grid-cols-3 gap-4">
          <ListEditorCard listKey="segmento" label="Segmento" values={bundle.lists.segmento} />
          <ListEditorCard listKey="campus" label="Campus" values={bundle.lists.campus} />
          <PilarEditor bundle={bundle} />
        </div>
      </TabsContent>

      <TabsContent value="nivel2" className="flex flex-col gap-4">
        <CampusFacultadMatrix bundle={bundle} />
        <div className="grid grid-cols-2 gap-4">
          <ListEditorCard listKey="edad" label="Edad" values={bundle.lists.edad} />
          <ListEditorCard listKey="senal" label="Tipo de Señal" values={bundle.lists.senal} />
        </div>
      </TabsContent>

      <TabsContent value="nivel3" className="grid grid-cols-2 gap-4">
        {NIVEL3_LISTS.map(({ key, label }) => (
          <ListEditorCard key={key} listKey={key} label={label} values={bundle.lists[key]} />
        ))}
      </TabsContent>
    </Tabs>
  );
}
