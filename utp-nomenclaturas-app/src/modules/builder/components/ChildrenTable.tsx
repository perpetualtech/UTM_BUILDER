import { Button } from "@/modules/core/components/design-system/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/modules/core/components/design-system/table";

interface ChildRow {
  uuid: string;
  name: string;
}

interface ChildrenTableProps<T extends ChildRow> {
  items: T[];
  emptyLabel: string;
  onOpen?: (item: T) => void;
  onDuplicate: (item: T) => void;
  onDelete: (item: T) => void;
}

/** §8.2 del SDD: Builder → ChildrenTable — hijos existentes en cada nivel del drill-down. */
export function ChildrenTable<T extends ChildRow>({
  items,
  emptyLabel,
  onOpen,
  onDuplicate,
  onDelete,
}: ChildrenTableProps<T>) {
  if (items.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Nombre</TableHead>
          <TableHead className="w-0 text-right">Acciones</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((item) => (
          <TableRow key={item.uuid}>
            <TableCell className="font-mono text-xs">{item.name}</TableCell>
            <TableCell className="flex justify-end gap-2">
              {onOpen ? (
                <Button variant="outline" size="sm" onClick={() => onOpen(item)}>
                  Abrir
                </Button>
              ) : null}
              <Button variant="outline" size="sm" onClick={() => onDuplicate(item)}>
                Duplicar
              </Button>
              <Button variant="destructive" size="sm" onClick={() => onDelete(item)}>
                Eliminar
              </Button>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  );
}
