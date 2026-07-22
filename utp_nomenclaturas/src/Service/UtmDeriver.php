<?php

namespace Drupal\utp_nomenclaturas\Service;

/**
 * Deriva los parámetros UTM de un anuncio a partir de su campaña — §3.4 y
 * Anexo D del SDD. Puerto 1:1 de `deriveUtm()`/`googleSub()`/
 * `presetForCampaign()` de UTP-Nomenclaturas.html (líneas 1369-1386).
 *
 * A diferencia de NameBuilder (ADR-003: el cliente puede previsualizar), la
 * UTM derivada solo se muestra para anuncios ya creados — no hay preview de
 * UTM en el cliente antes de guardar, así que este servicio es autoritativo
 * y vive solo en el servidor; el único puerto TS de esta lógica es el mock
 * de MSW (frontend), no una previsualización de producción.
 */
class UtmDeriver {

  public function __construct(
    protected readonly DictionaryProvider $dictionaryProvider,
  ) {}

  /**
   * @param array $context Claves: medio, tipo_camp (de la Campaign),
   *   campaign_name, ad_set_name, ad_name, ad_url (puede ser NULL/''),
   *   meta_mode ('macro'|'hard'), default_url.
   *
   * @return array{plat: string, source: string, medium: string,
   *   campaign: string, term: string, content: string, params: string,
   *   url: string, full: string, sep: bool, where: string, ga4: string}|null
   *   NULL si `medio` no tiene preset asociado (campaña sin plataforma).
   */
  public function derive(array $context): ?array {
    $preset = $this->presetForCampaign((string) $context['medio'], (string) ($context['tipo_camp'] ?? ''));
    if ($preset === NULL) {
      return NULL;
    }

    $metaMode = $context['meta_mode'] ?? 'macro';
    $campaignName = (string) $context['campaign_name'];
    $adSetName = (string) $context['ad_set_name'];
    $adName = (string) $context['ad_name'];

    if ($preset['plat'] === 'Meta' || $preset['plat'] === 'Tiktok') {
      if ($metaMode === 'hard') {
        [$campaignValue, $termValue, $contentValue] = [$campaignName, $adSetName, $adName];
      }
      else {
        [$campaignValue, $termValue, $contentValue] = [$preset['campaign'], $preset['term'], $preset['content']];
      }
    }
    elseif ($preset['plat'] === 'LinkedIn') {
      [$campaignValue, $termValue, $contentValue] = [$campaignName, $adSetName, $adName];
    }
    else {
      // Google / DV360: campaign siempre hard, term/content del preset.
      [$campaignValue, $termValue, $contentValue] = [$campaignName, $preset['term'], $preset['content']];
    }

    $parts = [
      ['utm_source', $preset['source']],
      ['utm_medium', $preset['medium']],
      ['utm_campaign', $campaignValue],
    ];
    if ($termValue !== '') {
      $parts[] = ['utm_term', $termValue];
    }
    if ($contentValue !== '') {
      $parts[] = ['utm_content', $contentValue];
    }

    $params = implode('&', array_map(fn (array $p) => $p[0] . '=' . $p[1], $parts));
    $url = (string) ($context['ad_url'] ?: ($context['default_url'] ?? ''));
    $paste = $this->platPaste($preset['plat']);

    return [
      'plat' => $preset['plat'],
      'source' => $preset['source'],
      'medium' => $preset['medium'],
      'campaign' => $campaignValue,
      'term' => $termValue,
      'content' => $contentValue,
      'params' => $params,
      'url' => $url,
      'full' => $this->joinUrl($url, $params),
      'sep' => $paste['sep'],
      'where' => $paste['where'],
      'ga4' => $preset['ga4'],
    ];
  }

  /**
   * §3.4 ¶1: sub-preset de GoogleAds según `tipo_camp` (Anexo D: `googleSub`).
   */
  public function googleSub(string $tipoCamp): string {
    $t = mb_strtolower($tipoCamp);
    if (str_contains($t, 'pmax') || str_contains($t, 'performance')) {
      return 'google-pmax';
    }
    if (str_contains($t, 'demand')) {
      return 'google-demandgen';
    }
    if (str_contains($t, 'video') || str_contains($t, 'youtube')) {
      return 'google-video';
    }
    if (str_contains($t, 'display')) {
      return 'google-display';
    }
    return 'google-search';
  }

  /**
   * @return array{plat: string, source: string, medium: string, campaign:
   *   string, term: string, content: string, ga4: string}|null
   */
  private function presetForCampaign(string $medio, string $tipoCamp): ?array {
    $bundle = $this->dictionaryProvider->getBundle();
    $presetKey = $medio === 'GoogleAds'
      ? $this->googleSub($tipoCamp)
      : ($bundle['medio_to_preset'][$medio] ?? NULL);

    if ($presetKey === NULL) {
      return NULL;
    }

    return $bundle['utm_presets'][$presetKey] ?? NULL;
  }

  private function platPaste(string $plat): array {
    $bundle = $this->dictionaryProvider->getBundle();
    return $bundle['plat_paste'][$plat] ?? ['sep' => TRUE, 'where' => ''];
  }

  /**
   * Anexo D: `joinUrl(base, qs)` — concatena base + query string, tolerando
   * que `base` ya tenga `?`/`&` colgantes.
   */
  private function joinUrl(string $base, string $qs): string {
    $base = trim($base);
    if ($base === '') {
      return $qs;
    }
    $sep = str_contains($base, '?') ? '&' : '?';
    return rtrim($base, '?&') . $sep . $qs;
  }

}
