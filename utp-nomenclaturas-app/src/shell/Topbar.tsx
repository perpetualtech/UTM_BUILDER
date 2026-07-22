interface TopbarProps {
  title: string;
}

/** §8.2 del SDD: Shell → Topbar. Título de la vista activa. */
export function Topbar({ title }: TopbarProps) {
  return (
    <header className="flex h-14 shrink-0 items-center border-b border-border bg-card px-6">
      <h1 className="text-base font-semibold text-foreground">{title}</h1>
    </header>
  );
}
