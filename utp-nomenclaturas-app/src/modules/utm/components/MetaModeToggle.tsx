import { Button } from "@/modules/core/components/design-system/button";
import type { UtmConfig } from "@/modules/core/types/api";

interface MetaModeToggleProps {
  config: UtmConfig;
  onChange: (metaMode: UtmConfig["meta_mode"]) => void;
}

/** §3.4 del SDD: modo macro (tokens de plataforma) vs. hard (nombres reales) para Meta/Tiktok. */
export function MetaModeToggle({ config, onChange }: MetaModeToggleProps) {
  return (
    <div className="flex items-center gap-2 text-sm">
      <span className="text-muted-foreground">Meta/Tiktok:</span>
      <div className="flex gap-1 rounded-md border border-border p-0.5">
        <Button
          type="button"
          size="sm"
          variant={config.meta_mode === "macro" ? "default" : "ghost"}
          onClick={() => onChange("macro")}
        >
          Macro
        </Button>
        <Button
          type="button"
          size="sm"
          variant={config.meta_mode === "hard" ? "default" : "ghost"}
          onClick={() => onChange("hard")}
        >
          Hard
        </Button>
      </div>
    </div>
  );
}
