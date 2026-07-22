import { toast } from "sonner";
import { ApiError } from "@/modules/core/types/api";
import type { DictBundle } from "@/modules/core/types/api";
import { useUpdateCampusFacultad } from "@/modules/config/hooks/useConfigMutations";

interface CampusFacultadMatrixProps {
  bundle: DictBundle;
}

function isFacultadAtSede(bundle: DictBundle, facultad: string, sede: string): boolean {
  const restriccion = bundle.campus_facultad[facultad];
  return !restriccion || restriccion.includes(sede);
}

/**
 * D4 (§3.2, Config Nivel 2) — matriz Campus×Facultad, puerto de
 * `renderCfgNivel2()`/`toggleFacultadAtSede()` del HTML de referencia.
 * Cada toggle manda el mapa completo (facultad => sedes permitidas) al
 * servidor, que normaliza la regla "cobertura total → sin restricción"
 * (AC de la Fase 4: editar la matriz impacta D3 en vivo).
 */
export function CampusFacultadMatrix({ bundle }: CampusFacultadMatrixProps) {
  const updateCampusFacultad = useUpdateCampusFacultad();
  const facultades = bundle.lists.facultad.filter((f) => f !== "virtual");

  function handleToggle(facultad: string, sede: string, checked: boolean) {
    const current = bundle.campus_facultad[facultad];
    const nextForFacultad = current
      ? (checked ? Array.from(new Set([...current, sede])) : current.filter((s) => s !== sede))
      : (checked ? [...bundle.sedes_especificas] : bundle.sedes_especificas.filter((s) => s !== sede));

    const nextMatrix = { ...bundle.campus_facultad, [facultad]: nextForFacultad };
    updateCampusFacultad.mutate(nextMatrix, {
      onError: (error) => toast.error(error instanceof ApiError ? error.body.error : "No se pudo actualizar la matriz."),
    });
  }

  return (
    <div className="rounded-lg border border-border bg-card">
      <div className="border-b border-border px-4 py-3">
        <h3 className="text-sm font-semibold text-foreground">Matriz Campus × Facultad · Nivel 2</h3>
        <p className="text-xs text-muted-foreground">
          Marca qué facultades se ofrecen en cada sede. Desmarca para restringir. Los cambios se aplican al Constructor en tiempo real.
        </p>
      </div>
      <div className="overflow-x-auto p-4">
        <table className="w-full min-w-max border-collapse text-xs">
          <thead>
            <tr>
              <th className="border-b border-border p-2 text-left font-semibold">Grupo</th>
              <th className="border-b border-border p-2 text-left font-semibold">Sede</th>
              {facultades.map((facultad) => (
                <th key={facultad} className="border-b border-border p-2 text-left font-semibold">
                  {bundle.facultad_nombre[facultad] ?? facultad}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {bundle.sedes_especificas.map((sede) => (
              <tr key={sede}>
                <td className="border-b border-border p-2 text-muted-foreground">{bundle.sede_grupo[sede] ?? "—"}</td>
                <td className="border-b border-border p-2 font-mono">{sede}</td>
                {facultades.map((facultad) => {
                  const checkboxId = `matrix-${facultad}-${sede}`;
                  return (
                    <td key={facultad} className="border-b border-border p-2">
                      <label htmlFor={checkboxId} className="sr-only">
                        {facultad} en {sede}
                      </label>
                      <input
                        id={checkboxId}
                        type="checkbox"
                        checked={isFacultadAtSede(bundle, facultad, sede)}
                        onChange={(e) => handleToggle(facultad, sede, e.target.checked)}
                      />
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
