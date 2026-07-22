interface LivePreviewProps {
  name: string;
}

/** §8.2/§8.4 del SDD — preview del nombre derivado en vivo (useNamePreview). */
export function LivePreview({ name }: LivePreviewProps) {
  return (
    <div className="rounded-md border border-dashed border-border bg-muted/40 px-3 py-2 font-mono text-sm">
      {name || <span className="text-muted-foreground">El nombre se arma a medida que completas los campos…</span>}
    </div>
  );
}
