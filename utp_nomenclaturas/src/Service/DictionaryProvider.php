<?php

namespace Drupal\utp_nomenclaturas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Expone el bundle de diccionario + constantes UTM (Anexo A/B del SDD) y
 * resuelve las condicionales D1-D3 (§3.2). D4 (matriz editable
 * Campus×Facultad) es Fase 4 — aquí solo se LEE `campus_facultad`.
 */
class DictionaryProvider {

  /**
   * Campos de Campaign condicionados por etapa (D1) — claves de config
   * (camelCase, Anexo A) que difieren del nombre snake_case del campo de
   * la entidad (obj_camp/obj_plat/tipo_camp).
   */
  private const ETAPA_CONDITIONED_FIELDS = ['medio', 'objCamp', 'objPlat', 'tipoCamp'];

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

  /**
   * D1 (§3.2): opciones válidas de un campo condicionado por etapa.
   *
   * @param string $etapa Código de etapa (upper|middle|lower).
   * @param string $field Clave de config: medio|objCamp|objPlat|tipoCamp
   *   (camelCase — la misma forma que en `etapa_conditionals`, NO el
   *   nombre snake_case del campo de la entidad).
   *
   * @throws \InvalidArgumentException si $field no es una de las 4 claves
   *   condicionadas por etapa.
   */
  public function getOptionsForEtapa(string $etapa, string $field): array {
    if (!in_array($field, self::ETAPA_CONDITIONED_FIELDS, TRUE)) {
      throw new \InvalidArgumentException(sprintf(
        "'%s' no es un campo condicionado por etapa. Válidos: %s",
        $field,
        implode(', ', self::ETAPA_CONDITIONED_FIELDS)
      ));
    }

    $bundle = $this->getBundle();
    return $bundle['etapa_conditionals'][$field][$etapa] ?? [];
  }

  /**
   * D2 (§3.2): pilares válidos para un segmento.
   */
  public function getPilaresForSegmento(string $segmento): array {
    $bundle = $this->getBundle();
    return $bundle['segmento_pilar'][$segmento] ?? [];
  }

  /**
   * D2 (§3.2): valida la combinación pilar+segmento.
   *
   * No hace falta un caso especial para "empleabilidad" — el dato sembrado
   * ya lo excluye de segmento_pilar.adultos, así que el chequeo de
   * pertenencia alcanza para cumplir esa regla.
   */
  public function isValidPilarSegmentoCombination(string $pilar, string $segmento): bool {
    return in_array($pilar, $this->getPilaresForSegmento($segmento), TRUE);
  }

  /**
   * D3 (§3.2): facultades disponibles para una ubicación.
   *
   * Una facultad sin restricción (no aparece en `campus_facultad`) siempre
   * está disponible. Una facultad restringida está disponible si
   * `$ubicacion` es una de sus sedes permitidas, o es un grupo
   * (`ubicacion_grupo`) cuyos miembros intersectan esas sedes.
   */
  public function getFacultadesForUbicacion(string $ubicacion): array {
    $bundle = $this->getBundle();
    $todasLasFacultades = $bundle['lists']['facultad'] ?? [];
    $campusFacultad = $bundle['campus_facultad'] ?? [];
    $ubicacionGrupo = $bundle['ubicacion_grupo'] ?? [];

    $disponibles = [];
    foreach ($todasLasFacultades as $facultad) {
      if ($this->isFacultadDisponible($facultad, $ubicacion, $campusFacultad, $ubicacionGrupo)) {
        $disponibles[] = $facultad;
      }
    }

    return $disponibles;
  }

  /**
   * D3 (§3.2): valida si una facultad concreta está disponible en una ubicación.
   */
  public function isFacultadValidForUbicacion(string $facultad, string $ubicacion): bool {
    $bundle = $this->getBundle();
    return $this->isFacultadDisponible(
      $facultad,
      $ubicacion,
      $bundle['campus_facultad'] ?? [],
      $bundle['ubicacion_grupo'] ?? []
    );
  }

  private function isFacultadDisponible(string $facultad, string $ubicacion, array $campusFacultad, array $ubicacionGrupo): bool {
    if (!isset($campusFacultad[$facultad])) {
      // Sin restricción → disponible en cualquier ubicación.
      return TRUE;
    }

    $sedesPermitidas = $campusFacultad[$facultad];
    if (in_array($ubicacion, $sedesPermitidas, TRUE)) {
      return TRUE;
    }

    $miembrosGrupo = $ubicacionGrupo[$ubicacion] ?? NULL;
    if ($miembrosGrupo !== NULL) {
      return (bool) array_intersect($miembrosGrupo, $sedesPermitidas);
    }

    return FALSE;
  }

  /**
   * Verifica si un código existe en una lista plana activa del diccionario.
   *
   * @param string $listKey Clave en bundle['lists'] (ej. "segmento", "edad").
   *   NO usar con medio/obj_camp/obj_plat/tipo_camp/pillar_code — esos no
   *   son listas planas, se validan vía D1/D2.
   *
   * @throws \InvalidArgumentException si $listKey no es una lista conocida.
   */
  public function codeExistsInList(string $listKey, string $value): bool {
    $bundle = $this->getBundle();
    if (!array_key_exists($listKey, $bundle['lists'])) {
      throw new \InvalidArgumentException(sprintf("La lista '%s' no existe en el diccionario.", $listKey));
    }

    return in_array($value, $bundle['lists'][$listKey], TRUE);
  }

}
