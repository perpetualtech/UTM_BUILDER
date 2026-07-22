<?php

namespace Drupal\Tests\utp_nomenclaturas\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\utp_nomenclaturas\Service\DictionaryProvider;
use Drupal\utp_nomenclaturas\Service\UtmDeriver;

/**
 * Puerto 1:1 de `deriveUtm()`/`googleSub()`/`presetForCampaign()` del HTML
 * de referencia (líneas 1369-1386) — §3.4/Anexo D del SDD. Se mockea
 * DictionaryProvider (en vez de bootstrapear Drupal) porque solo se
 * necesita su `getBundle()`, ya verificado byte-a-byte contra Anexo B en
 * DictionaryBundleTest (Kernel, Fase 0).
 *
 * @group utp_nomenclaturas
 */
class UtmDeriverTest extends UnitTestCase {

  protected UtmDeriver $utmDeriver;

  protected function setUp(): void {
    parent::setUp();

    $dictionaryProvider = $this->createMock(DictionaryProvider::class);
    $dictionaryProvider->method('getBundle')->willReturn($this->anexoBBundle());

    $this->utmDeriver = new UtmDeriver($dictionaryProvider);
  }

  private function baseContext(array $overrides = []): array {
    return $overrides + [
      'medio' => 'Meta',
      'tipo_camp' => '',
      'campaign_name' => 'jovenes_upper_lima_meta_awareness_alcance_video_calidad',
      'ad_set_name' => 'j1_lima_ing_broad_gaming-tech',
      'ad_name' => 'video_marca_testimonial_estudia-trabaja_no-carreras_ene26',
      'ad_url' => 'https://utp.edu.pe/landing',
      'meta_mode' => 'macro',
      'default_url' => '',
    ];
  }

  // ---- Meta / Tiktok: macro vs hard ---------------------------------

  public function testMetaMacroModeUsesTemplateTokens(): void {
    $row = $this->utmDeriver->derive($this->baseContext());
    $this->assertSame('facebook', $row['source']);
    $this->assertSame('cpc', $row['medium']);
    $this->assertSame('{{campaign.name}}', $row['campaign']);
    $this->assertSame('{{adset.name}}', $row['term']);
    $this->assertSame('{{ad.name}}', $row['content']);
    $this->assertSame('Paid Social', $row['ga4']);
    $this->assertTrue($row['sep']);
  }

  public function testMetaHardModeUsesRealNames(): void {
    $context = $this->baseContext(['meta_mode' => 'hard']);
    $row = $this->utmDeriver->derive($context);
    $this->assertSame($context['campaign_name'], $row['campaign']);
    $this->assertSame($context['ad_set_name'], $row['term']);
    $this->assertSame($context['ad_name'], $row['content']);
  }

  public function testTiktokMacroModeUsesTemplateTokens(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'Tiktok']));
    $this->assertSame('tiktok', $row['source']);
    $this->assertSame('__CAMPAIGN_NAME__', $row['campaign']);
    $this->assertSame('__AID_NAME__', $row['term']);
    $this->assertSame('__CID_NAME__', $row['content']);
  }

  public function testTiktokHardModeUsesRealNames(): void {
    $context = $this->baseContext(['medio' => 'Tiktok', 'meta_mode' => 'hard']);
    $row = $this->utmDeriver->derive($context);
    $this->assertSame($context['campaign_name'], $row['campaign']);
  }

  // ---- LinkedIn: siempre hard ----------------------------------------

  public function testLinkedInAlwaysUsesRealNamesRegardlessOfMetaMode(): void {
    $context = $this->baseContext(['medio' => 'LinkedIn', 'meta_mode' => 'macro']);
    $row = $this->utmDeriver->derive($context);
    $this->assertSame($context['campaign_name'], $row['campaign']);
    $this->assertSame($context['ad_set_name'], $row['term']);
    $this->assertSame($context['ad_name'], $row['content']);
    $this->assertSame('linkedin', $row['source']);
  }

  // ---- GoogleAds: sub-preset por tipo_camp (googleSub) ---------------

  public function testGoogleSearchIsDefaultSubPreset(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => 'Search']));
    $this->assertSame('Paid Search', $row['ga4']);
    $this->assertSame('{keyword}', $row['term']);
    $this->assertSame('{creative}', $row['content']);
  }

  public function testGooglePmaxSubPreset(): void {
    foreach (['PMAX', 'Performance Max'] as $tipoCamp) {
      $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => $tipoCamp]));
      $this->assertSame('Cross-network', $row['ga4']);
      $this->assertSame('', $row['term'], "tipo_camp='$tipoCamp' no debe tener utm_term.");
      $this->assertSame('{resource group}', $row['content']);
      // term vacío → no debe aparecer en params.
      $this->assertStringNotContainsString('utm_term=', $row['params']);
    }
  }

  public function testGoogleDemandGenSubPreset(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => 'Demand-Gen']));
    $this->assertSame('Cross-network / Paid', $row['ga4']);
    $this->assertSame('{conjuntodeanuncio}', $row['content']);
  }

  public function testGoogleVideoSubPreset(): void {
    foreach (['Video', 'Youtube'] as $tipoCamp) {
      $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => $tipoCamp]));
      $this->assertSame('Video', $row['ga4']);
      $this->assertSame('cpv', $row['medium']);
    }
  }

  public function testGoogleDisplaySubPreset(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => 'Display']));
    $this->assertSame('Display', $row['ga4']);
    $this->assertSame('{placement}', $row['term']);
  }

  public function testGoogleCampaignIsAlwaysHardRegardlessOfMetaMode(): void {
    $context = $this->baseContext(['medio' => 'GoogleAds', 'tipo_camp' => 'Search', 'meta_mode' => 'macro']);
    $row = $this->utmDeriver->derive($context);
    $this->assertSame($context['campaign_name'], $row['campaign']);
  }

  // ---- DV360 ----------------------------------------------------------

  public function testDv360UsesHardCampaignAndDoesNotSeparateParamsFromUrl(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'DV360', 'meta_mode' => 'macro']));
    $this->assertSame('dv360', $row['source']);
    $this->assertSame('${CREATIVE_ID}', $row['term']);
    $this->assertSame('${LINE_ITEM_ID}', $row['content']);
    // §3.4 ¶4: DV360 es la única plataforma con sep=false (URL+params juntos).
    $this->assertFalse($row['sep']);
  }

  // ---- URL / joinUrl ----------------------------------------------------

  public function testUsesDefaultUrlWhenAdHasNoUrl(): void {
    $context = $this->baseContext(['ad_url' => '', 'default_url' => 'https://utp.edu.pe/default']);
    $row = $this->utmDeriver->derive($context);
    $this->assertSame('https://utp.edu.pe/default', $row['url']);
    $this->assertStringStartsWith('https://utp.edu.pe/default?', $row['full']);
  }

  public function testJoinUrlAppendsAmpersandWhenUrlAlreadyHasQueryString(): void {
    $context = $this->baseContext(['ad_url' => 'https://utp.edu.pe/landing?ref=ig']);
    $row = $this->utmDeriver->derive($context);
    $this->assertStringContainsString('landing?ref=ig&utm_source=', $row['full']);
  }

  // ---- medio sin preset -------------------------------------------------

  public function testReturnsNullWhenMedioHasNoPreset(): void {
    $row = $this->utmDeriver->derive($this->baseContext(['medio' => 'Email']));
    $this->assertNull($row);
  }

  /**
   * Mismo bloque que Anexo B (verificado 1:1 en DictionaryBundleTest, Fase 0).
   */
  private function anexoBBundle(): array {
    return [
      'utm_presets' => [
        'meta' => ['plat' => 'Meta', 'source' => 'facebook', 'medium' => 'cpc', 'campaign' => '{{campaign.name}}', 'term' => '{{adset.name}}', 'content' => '{{ad.name}}', 'ga4' => 'Paid Social'],
        'tiktok' => ['plat' => 'Tiktok', 'source' => 'tiktok', 'medium' => 'cpc', 'campaign' => '__CAMPAIGN_NAME__', 'term' => '__AID_NAME__', 'content' => '__CID_NAME__', 'ga4' => 'Paid Social'],
        'google-search' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '{keyword}', 'content' => '{creative}', 'ga4' => 'Paid Search'],
        'google-pmax' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{resource group}', 'ga4' => 'Cross-network'],
        'google-demandgen' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{conjuntodeanuncio}', 'ga4' => 'Cross-network / Paid'],
        'google-video' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpv', 'campaign' => '{campaignid}', 'term' => '', 'content' => '{creative}', 'ga4' => 'Video'],
        'google-display' => ['plat' => 'GoogleAds', 'source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '{placement}', 'content' => '{creative}', 'ga4' => 'Display'],
        'dv360' => ['plat' => 'DV360', 'source' => 'dv360', 'medium' => 'display', 'campaign' => 'HARD', 'term' => '${CREATIVE_ID}', 'content' => '${LINE_ITEM_ID}', 'ga4' => 'Display'],
        'linkedin' => ['plat' => 'LinkedIn', 'source' => 'linkedin', 'medium' => 'cpc', 'campaign' => 'HARD', 'term' => 'HARD', 'content' => 'HARD', 'ga4' => 'Paid Social'],
      ],
      'medio_to_preset' => ['Meta' => 'meta', 'Tiktok' => 'tiktok', 'GoogleAds' => 'google-search', 'DV360' => 'dv360', 'LinkedIn' => 'linkedin'],
      'plat_paste' => [
        'Meta' => ['sep' => TRUE, 'where' => 'Meta › Seguimiento › Parámetros de URL (URL limpia en «URL del sitio web»)'],
        'Tiktok' => ['sep' => TRUE, 'where' => 'TikTok › Anuncio › Edit URL parameters (Auto-attach OFF)'],
        'GoogleAds' => ['sep' => TRUE, 'where' => 'Google › Sufijo de la URL final'],
        'DV360' => ['sep' => FALSE, 'where' => 'DV360 › Creative › Landing page URL (URL + parámetros juntos)'],
        'LinkedIn' => ['sep' => TRUE, 'where' => 'LinkedIn › Parámetro de URL del anuncio'],
      ],
    ];
  }

}
