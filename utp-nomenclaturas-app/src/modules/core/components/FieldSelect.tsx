import { useId } from "react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/modules/core/components/design-system/select";

interface FieldSelectProps {
  label: string;
  value: string;
  options: string[];
  onChange: (value: string) => void;
  disabled?: boolean;
  placeholder?: string;
  hint?: string;
}

/**
 * Select genérico para los campos del Builder (§8.4 del SDD: "Cada
 * FieldSelect recibe sus opciones ya filtradas por el hook; nunca decide
 * sola"). Las opciones y el estado disabled los resuelve
 * useDependentOptions, no este componente.
 */
export function FieldSelect({
  label,
  value,
  options,
  onChange,
  disabled = false,
  placeholder = "Selecciona…",
  hint,
}: FieldSelectProps) {
  const triggerId = useId();

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={triggerId} className="text-sm font-medium text-foreground">
        {label}
      </label>
      {/*
        value siempre es un string definido (nunca `undefined`) para que el
        Select quede controlado desde el primer render — alternar entre
        `undefined` y string dispara el warning de Base UI de "uncontrolled
        a controlled".
      */}
      <Select
        value={value}
        onValueChange={(next) => onChange(next ?? "")}
        disabled={disabled || options.length === 0}
      >
        <SelectTrigger id={triggerId} className="w-full">
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option} value={option}>
              {option}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
    </div>
  );
}
