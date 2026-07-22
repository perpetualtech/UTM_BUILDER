import { Tabs, TabsList, TabsTrigger } from "@/modules/core/components/design-system/tabs";
import { getPilaresForSegmento } from "@/modules/core/lib/dictionaryRules";
import type { DictBundle } from "@/modules/core/types/api";

interface PillarTabsProps {
  bundle: DictBundle;
  segmento: string;
  pillar: string;
  onSegmentoChange: (segmento: string) => void;
  onPillarChange: (pillar: string) => void;
}

/**
 * §2.1/§3.2 (D2) del SDD: segmento → pilar. Si el pilar elegido es
 * "empleabilidad" (exclusivo de jóvenes), el toggle "adultos" se
 * deshabilita — misma regla de UI que el HTML de referencia.
 */
export function PillarTabs({ bundle, segmento, pillar, onSegmentoChange, onPillarChange }: PillarTabsProps) {
  const pilares = segmento ? getPilaresForSegmento(bundle, segmento) : [];
  const adultosDisabled = pillar === "empleabilidad";

  return (
    <div className="flex flex-col gap-3">
      <Tabs value={segmento} onValueChange={onSegmentoChange}>
        <TabsList>
          <TabsTrigger value="adultos" disabled={adultosDisabled}>
            Adultos
          </TabsTrigger>
          <TabsTrigger value="jovenes">Jóvenes</TabsTrigger>
        </TabsList>
      </Tabs>

      {segmento ? (
        <Tabs value={pillar} onValueChange={onPillarChange}>
          <TabsList>
            {pilares.map((p) => (
              <TabsTrigger key={p} value={p}>
                {p}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
      ) : null}
    </div>
  );
}
