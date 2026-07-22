import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/modules/core/components/design-system/tabs";
import { ManualUtmBuilder } from "@/modules/utm/components/ManualUtmBuilder";
import { ManualUtmList } from "@/modules/utm/components/ManualUtmList";
import { PaidUtmTree } from "@/modules/utm/components/PaidUtmTree";

/** §7.3/§8 del SDD: módulo UTM — tabs Paid (derivadas) / Manual (tráfico sin pauta). */
export function UtmPage() {
  return (
    <Tabs defaultValue="paid" className="flex flex-col gap-4">
      <TabsList>
        <TabsTrigger value="paid">Paid</TabsTrigger>
        <TabsTrigger value="manual">Manual</TabsTrigger>
      </TabsList>

      <TabsContent value="paid">
        <PaidUtmTree />
      </TabsContent>

      <TabsContent value="manual" className="flex flex-col gap-4">
        <ManualUtmBuilder />
        <ManualUtmList />
      </TabsContent>
    </Tabs>
  );
}
