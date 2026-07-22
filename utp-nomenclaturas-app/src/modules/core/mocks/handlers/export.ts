import ExcelJS from "exceljs";
import { HttpResponse, http } from "msw";
import { respond } from "@/modules/core/mocks/handlers/respond";
import { deriveUtm } from "@/modules/core/lib/utmDeriver";
import {
  campaignsForExport,
  exportBackup,
  flattenPaidUtms,
  getDictionary,
  getUtmConfig,
  importBackup,
  listManualUtms,
  type ExportCampaignTree,
} from "@/modules/core/mocks/store";

/**
 * Handlers de export/import (§3.5/§10 del SDD). A diferencia del resto de
 * los mocks (que solo reproducen el contrato HTTP), acá se genera un
 * `.xlsx` REAL con `exceljs` — así la verificación en navegador puede
 * descargar y abrir el archivo de verdad, no solo inspeccionar JSON. En
 * producción el Excel lo genera PhpSpreadsheet (server-side); esto es
 * puramente para probar el flujo de descarga en dev, no una duplicación
 * de la lógica de negocio (columnas/hojas siguen §3.5, pero no hay
 * segunda implementación de UtmDeriver — se reusa utmDeriver.ts).
 */

const API_BASE = "/api/utp-nomenclaturas/v1";
const XLSX_MIME = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
const PLATFORMS = ["Meta", "Tiktok", "DV360", "LinkedIn", "GoogleAds"];

async function buildCampaignsWorkbook(tree: ExportCampaignTree[]): Promise<ExcelJS.Buffer> {
  const utmConfig = getUtmConfig();
  const headers = ["Medio", "Nombre de Campaña", "Conjunto de Anuncios", "Anuncio", "URL de destino", "Parámetros UTM (copiar/pegar)", "Dónde pegar"];
  const widths = [12, 54, 54, 58, 22, 62, 44];

  const workbook = new ExcelJS.Workbook();
  for (const plat of PLATFORMS) {
    const campaigns = tree.filter((c) => c.medio === plat);
    if (!campaigns.length) continue;

    const sheet = workbook.addWorksheet(plat.slice(0, 31));
    sheet.columns = headers.map((header, i) => ({ header, width: widths[i] }));

    for (const campaign of campaigns) {
      let firstCamp = true;
      if (!campaign.ad_sets.length) {
        sheet.addRow([plat, campaign.name, "", "", "", "", ""]);
        continue;
      }
      for (const adSet of campaign.ad_sets) {
        let firstGroup = true;
        if (!adSet.ads.length) {
          sheet.addRow([plat, firstCamp ? campaign.name : "", adSet.name, "", "", "", ""]);
          firstCamp = false;
          continue;
        }
        for (const ad of adSet.ads) {
          const derived = deriveUtm(getDictionary(), {
            medio: campaign.medio,
            tipo_camp: campaign.tipo_camp,
            campaign_name: campaign.name,
            ad_set_name: adSet.name,
            ad_name: ad.name,
            ad_url: ad.url,
            meta_mode: utmConfig.meta_mode,
            default_url: utmConfig.default_url,
          });
          sheet.addRow([
            plat,
            firstCamp ? campaign.name : "",
            firstGroup ? adSet.name : "",
            ad.name,
            derived?.url ?? "",
            derived?.params ?? "",
            derived?.where ?? "",
          ]);
          firstCamp = false;
          firstGroup = false;
        }
      }
    }
  }

  return workbook.xlsx.writeBuffer();
}

async function buildUtmsWorkbook(): Promise<ExcelJS.Buffer> {
  const headers = ["Modo", "Plataforma", "Campaña", "Conjunto", "Anuncio", "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "URL de destino", "Parámetros (copiar/pegar)", "Dónde pegar", "Pegado"];
  const widths = [7, 11, 46, 46, 50, 12, 9, 46, 20, 20, 44, 60, 44, 22];

  const paidRows = flattenPaidUtms().map((u) => [
    "paid", u.plat, u.campaign_name, u.ad_set_name, u.ad_name,
    u.source, u.medium, u.campaign, u.term, u.content,
    u.url, u.params, u.where, u.sep ? "campo aparte (URL limpia)" : "junto en la URL",
  ]);
  const manualRows = listManualUtms().map((m) => [
    "manual", "Manual", m.utm_campaign, "", "",
    m.utm_source, m.utm_medium, m.utm_campaign, m.utm_term, m.utm_content,
    m.url, m.qs, "URL completa (bio/enlace)", "junto en la URL",
  ]);
  const rowsAll = [...paidRows, ...manualRows];

  const workbook = new ExcelJS.Workbook();
  const sources = [...new Set(rowsAll.map((r) => r[5]).filter(Boolean))] as string[];
  for (const source of sources) {
    const sheet = workbook.addWorksheet(source.slice(0, 31));
    sheet.columns = headers.map((header, i) => ({ header, width: widths[i] }));
    rowsAll.filter((r) => r[5] === source).forEach((r) => sheet.addRow(r));
  }
  const consolidado = workbook.addWorksheet("Consolidado");
  consolidado.columns = headers.map((header, i) => ({ header, width: widths[i] }));
  rowsAll.forEach((r) => consolidado.addRow(r));

  return workbook.xlsx.writeBuffer();
}

function xlsxResponse(buffer: ExcelJS.Buffer, filename: string) {
  return new HttpResponse(new Blob([buffer], { type: XLSX_MIME }), {
    status: 200,
    headers: {
      "Content-Type": XLSX_MIME,
      "Content-Disposition": `attachment; filename="${filename}"`,
    },
  });
}

export const exportHandlers = [
  http.get(`${API_BASE}/export/campaigns.xlsx`, async ({ request }) => {
    const url = new URL(request.url);
    const uuids = url.searchParams.getAll("uuids[]");
    const tree = campaignsForExport({
      pillar: url.searchParams.get("pillar") ?? undefined,
      medio: url.searchParams.get("medio") ?? undefined,
      q: url.searchParams.get("q") ?? undefined,
      uuids: uuids.length ? uuids : undefined,
    });
    if (!tree.length) {
      return HttpResponse.json({ error: "Selecciona al menos una campaña o ajusta los filtros.", code: "VALIDATION_FAILED", details: {} }, { status: 422 });
    }
    const buffer = await buildCampaignsWorkbook(tree);
    return xlsxResponse(buffer, `UTP_Nomenclaturas_${new Date().toISOString().slice(0, 10)}.xlsx`);
  }),

  http.get(`${API_BASE}/export/utms.xlsx`, async () => {
    if (!flattenPaidUtms().length && !listManualUtms().length) {
      return HttpResponse.json({ error: "No hay UTMs para exportar.", code: "VALIDATION_FAILED", details: {} }, { status: 422 });
    }
    const buffer = await buildUtmsWorkbook();
    return xlsxResponse(buffer, `UTP_UTMs_${new Date().toISOString().slice(0, 10)}.xlsx`);
  }),

  http.get(`${API_BASE}/export/backup.json`, () =>
    HttpResponse.json(exportBackup(), {
      headers: { "Content-Disposition": 'attachment; filename="UTP_nomenclaturas_backup.json"' },
    }),
  ),

  http.post(`${API_BASE}/import`, async ({ request }) => {
    const data = await request.json();
    return respond(() => importBackup(data as Parameters<typeof importBackup>[0]));
  }),
];
