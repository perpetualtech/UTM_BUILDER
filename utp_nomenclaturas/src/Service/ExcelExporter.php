<?php

namespace Drupal\utp_nomenclaturas\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera los 2 Excel del módulo (§3.5 del SDD), puerto 1:1 de
 * `exportExcel()` (líneas 1008-1048) y `exportUtmExcel()` (líneas
 * 1516-1529) de UTP-Nomenclaturas.html. Usa PhpSpreadsheet como reemplazo
 * server-side de SheetJS (§9.3).
 */
class ExcelExporter {

  private const PLATFORMS = ['Meta', 'Tiktok', 'DV360', 'LinkedIn', 'GoogleAds'];

  public function __construct(
    protected readonly UtmDeriver $utmDeriver,
  ) {}

  /**
   * Excel de nomenclaturas — una hoja por plataforma (orden PLATFORMS). El
   * nombre de campaña/conjunto solo aparece en la primera fila de cada
   * campaña/conjunto; campañas/conjuntos vacíos igual generan fila (§3.5).
   *
   * @param array $campaigns Cada elemento:
   *   {name, medio, tipo_camp, ad_sets: [{name, ads: [{name, url}]}]}.
   */
  public function buildCampaignsWorkbook(array $campaigns, string $metaMode, string $defaultUrl): Spreadsheet {
    $headers = ['Medio', 'Nombre de Campaña', 'Conjunto de Anuncios', 'Anuncio', 'URL de destino', 'Parámetros UTM (copiar/pegar)', 'Dónde pegar'];
    $widths = [12, 54, 54, 58, 22, 62, 44];

    $workbook = new Spreadsheet();
    $workbook->removeSheetByIndex(0);

    foreach (self::PLATFORMS as $plat) {
      $campsForPlat = array_values(array_filter($campaigns, fn (array $c) => $c['medio'] === $plat));
      if (!$campsForPlat) {
        continue;
      }

      $rows = [];
      foreach ($campsForPlat as $campaign) {
        $firstCamp = TRUE;
        if (!$campaign['ad_sets']) {
          $rows[] = [$plat, $campaign['name'], '', '', '', '', ''];
          continue;
        }
        foreach ($campaign['ad_sets'] as $adSet) {
          $firstGroup = TRUE;
          if (!$adSet['ads']) {
            $rows[] = [$plat, $firstCamp ? $campaign['name'] : '', $adSet['name'], '', '', '', ''];
            $firstCamp = FALSE;
            continue;
          }
          foreach ($adSet['ads'] as $ad) {
            $derived = $this->utmDeriver->derive([
              'medio' => $campaign['medio'],
              'tipo_camp' => $campaign['tipo_camp'],
              'campaign_name' => $campaign['name'],
              'ad_set_name' => $adSet['name'],
              'ad_name' => $ad['name'],
              'ad_url' => $ad['url'],
              'meta_mode' => $metaMode,
              'default_url' => $defaultUrl,
            ]);
            $rows[] = [
              $plat,
              $firstCamp ? $campaign['name'] : '',
              $firstGroup ? $adSet['name'] : '',
              $ad['name'],
              $derived['url'] ?? '',
              $derived['params'] ?? '',
              $derived['where'] ?? '',
            ];
            $firstCamp = FALSE;
            $firstGroup = FALSE;
          }
        }
      }

      $this->addSheet($workbook, $plat, $headers, $rows, $widths);
    }

    return $workbook;
  }

  /**
   * Excel de UTMs — una hoja por `utm_source` + hoja `Consolidado`. Incluye
   * paid (derivadas) + manuales (§3.5).
   *
   * @param array $paidRows Filas ya derivadas (shape de UtmController::listPaid).
   * @param array $manualUtms Filas de ManualUtm (shape de
   *   UtmController::manualUtmToArray).
   */
  public function buildUtmsWorkbook(array $paidRows, array $manualUtms): Spreadsheet {
    $headers = ['Modo', 'Plataforma', 'Campaña', 'Conjunto', 'Anuncio', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'URL de destino', 'Parámetros (copiar/pegar)', 'Dónde pegar', 'Pegado'];
    $widths = [7, 11, 46, 46, 50, 12, 9, 46, 20, 20, 44, 60, 44, 22];

    $rowsAll = [];
    foreach ($paidRows as $row) {
      $rowsAll[] = [
        'Modo' => 'paid',
        'Plataforma' => $row['plat'],
        'Campaña' => $row['campaign_name'],
        'Conjunto' => $row['ad_set_name'],
        'Anuncio' => $row['ad_name'],
        'utm_source' => $row['source'],
        'utm_medium' => $row['medium'],
        'utm_campaign' => $row['campaign'],
        'utm_term' => $row['term'] ?? '',
        'utm_content' => $row['content'] ?? '',
        'URL de destino' => $row['url'],
        'Parámetros (copiar/pegar)' => $row['params'],
        'Dónde pegar' => $row['where'],
        'Pegado' => $row['sep'] ? 'campo aparte (URL limpia)' : 'junto en la URL',
      ];
    }
    foreach ($manualUtms as $row) {
      $rowsAll[] = [
        'Modo' => 'manual',
        'Plataforma' => 'Manual',
        'Campaña' => $row['utm_campaign'] ?? '',
        'Conjunto' => '',
        'Anuncio' => '',
        'utm_source' => $row['utm_source'],
        'utm_medium' => $row['utm_medium'],
        'utm_campaign' => $row['utm_campaign'] ?? '',
        'utm_term' => $row['utm_term'] ?? '',
        'utm_content' => $row['utm_content'] ?? '',
        'URL de destino' => $row['url'],
        'Parámetros (copiar/pegar)' => $row['qs'],
        'Dónde pegar' => 'URL completa (bio/enlace)',
        'Pegado' => 'junto en la URL',
      ];
    }

    $workbook = new Spreadsheet();
    $workbook->removeSheetByIndex(0);

    $sources = [];
    foreach ($rowsAll as $row) {
      if ($row['utm_source'] !== '' && !in_array($row['utm_source'], $sources, TRUE)) {
        $sources[] = $row['utm_source'];
      }
    }
    foreach ($sources as $source) {
      $rowsForSource = array_values(array_filter($rowsAll, fn (array $r) => $r['utm_source'] === $source));
      $this->addSheet($workbook, mb_substr($source, 0, 31), $headers, array_map('array_values', $rowsForSource), $widths);
    }
    $this->addSheet($workbook, 'Consolidado', $headers, array_map('array_values', $rowsAll), $widths);

    return $workbook;
  }

  public function writeToString(Spreadsheet $workbook): string {
    $writer = new Xlsx($workbook);
    $stream = fopen('php://temp', 'r+');
    $writer->save($stream);
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);
    return $contents;
  }

  /**
   * @param string[] $headers
   * @param array<int, array<int, string>> $rows
   * @param int[] $widths
   */
  private function addSheet(Spreadsheet $workbook, string $title, array $headers, array $rows, array $widths): void {
    $sheet = $workbook->addSheet(new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($workbook, mb_substr($title, 0, 31)));
    $sheet->fromArray($headers, NULL, 'A1');
    if ($rows) {
      $sheet->fromArray($rows, NULL, 'A2');
    }
    foreach (array_values($widths) as $i => $width) {
      $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
    }
  }

}
