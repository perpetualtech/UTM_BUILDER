import { Card, CardContent, CardHeader, CardTitle } from "@/modules/core/components/design-system/card";

interface KpiTileProps {
  label: string;
  value: number;
}

/** §4/§8.3 del SDD — tile de KPI del Dashboard. */
export function KpiTile({ label, value }: KpiTileProps) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-3xl font-semibold text-foreground">{value}</p>
      </CardContent>
    </Card>
  );
}
