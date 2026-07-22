import { dictDef } from "@/modules/dictionary/lib/dictDef";

/**
 * §7 del SDD: vista Diccionario — puramente de referencia/read-only,
 * puerto de `renderDict()` del HTML de referencia. A diferencia de
 * Config (Nivel 1/2/3), acá no hay mutaciones ni llamadas a la API.
 */
export function DictionaryPage() {
  return (
    <div className="flex flex-col gap-6">
      <section className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-foreground">Definición de campos</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          {dictDef.fields.map((field) => (
            <div key={field.key} className="rounded-lg border border-border bg-card p-3">
              <div className="mb-1 flex items-baseline gap-2">
                <code className="rounded bg-muted px-1.5 py-0.5 text-xs font-semibold">{field.key}</code>
                <span className="text-sm font-medium text-foreground">{field.label}</span>
              </div>
              <p className="text-xs text-muted-foreground">{field.desc}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-foreground">Categorías y valores</h2>
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {dictDef.categories.map((category) => (
            <div key={category.title} className="rounded-lg border border-border bg-card">
              <div className="border-b border-border px-4 py-3">
                <h3 className="text-sm font-semibold text-foreground">{category.title}</h3>
                <p className="text-xs text-muted-foreground">{category.desc}</p>
              </div>
              <div className="flex flex-col divide-y divide-border">
                {category.items.map((item) => (
                  <div key={item.key} className="px-4 py-2.5">
                    <div className="mb-0.5 flex items-baseline gap-2">
                      <code className="text-xs font-semibold text-primary">{item.key}</code>
                      <span className="text-sm font-medium text-foreground">{item.label}</span>
                    </div>
                    <p className="text-xs text-muted-foreground">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
