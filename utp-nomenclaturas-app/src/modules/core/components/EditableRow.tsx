import { useState } from "react";
import { Input } from "@/modules/core/components/design-system/input";

interface EditableRowProps {
  value: string;
  onSave: (value: string) => void;
  className?: string;
}

/** §8.3 del SDD — edición inline de nombre (usado en Repository). */
export function EditableRow({ value, onSave, className }: EditableRowProps) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(value);

  if (!editing) {
    return (
      <button
        type="button"
        className={`cursor-text truncate text-left font-mono text-xs hover:underline ${className ?? ""}`}
        onClick={() => {
          setDraft(value);
          setEditing(true);
        }}
      >
        {value}
      </button>
    );
  }

  function commit() {
    setEditing(false);
    const trimmed = draft.trim();
    if (trimmed && trimmed !== value) {
      onSave(trimmed);
    }
  }

  return (
    <Input
      autoFocus
      value={draft}
      onChange={(e) => setDraft(e.target.value)}
      onBlur={commit}
      onKeyDown={(e) => {
        if (e.key === "Enter") commit();
        if (e.key === "Escape") setEditing(false);
      }}
      className="h-7 font-mono text-xs"
    />
  );
}
