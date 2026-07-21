<?php

namespace Drupal\utp_nomenclaturas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Expone el bundle de diccionario + constantes UTM (Anexo A/B del SDD).
 *
 * En Fase 0 solo LEE la config sembrada y la arma en un array con la misma
 * forma que el Anexo A/B — no resuelve condicionales D1-D4 ni valida
 * códigos, eso llega completo en Fase 1 (§7.1: GET /config del contrato
 * de API).
 */
class DictionaryProvider {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Devuelve el bundle completo: listas + condicionales + mapas + UTM.
   *
   * @return array<string, mixed>
   */
  public function getBundle(): array {
    $dictionary = $this->configFactory->get('utp_nomenclaturas.dictionary');
    $utmPresets = $this->configFactory->get('utp_nomenclaturas.utm_presets');

    return [
      'lists' => $dictionary->get('lists') ?? [],
      'etapa_conditionals' => $dictionary->get('etapa_conditionals') ?? [],
      'segmento_pilar' => $dictionary->get('segmento_pilar') ?? [],
      'facultad_nombre' => $dictionary->get('facultad_nombre') ?? [],
      'campus_facultad' => $dictionary->get('campus_facultad') ?? [],
      'ubicacion_grupo' => $dictionary->get('ubicacion_grupo') ?? [],
      'sedes_especificas' => $dictionary->get('sedes_especificas') ?? [],
      'sede_grupo' => $dictionary->get('sede_grupo') ?? [],
      'platforms' => $dictionary->get('platforms') ?? [],
      'utm_presets' => $utmPresets->get('utm_presets') ?? [],
      'medio_to_preset' => $utmPresets->get('medio_to_preset') ?? [],
      'plat_paste' => $utmPresets->get('plat_paste') ?? [],
      'ux_sources' => $utmPresets->get('ux_sources') ?? [],
      'ux_mediums' => $utmPresets->get('ux_mediums') ?? [],
      'reserved_src' => $utmPresets->get('reserved_src') ?? [],
    ];
  }

}
