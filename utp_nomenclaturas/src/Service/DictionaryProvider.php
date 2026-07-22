<?php

namespace Drupal\utp_nomenclaturas\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\utp_nomenclaturas\Exception\DuplicateNameException;
use Drupal\utp_nomenclaturas\Exception\ValidationException;

/**
 * Expone el bundle de diccionario + constantes UTM (Anexo A/B del SDD),
 * resuelve las condicionales D1-D4 (§3.2) y mutación de la Configuración
 * Nivel 1/2/3 (§7.1, Fase 4) sobre `utp_nomenclaturas.dictionary`.
 */
class DictionaryProvider {

  /**
   * Campos de Campaign condicionados por etapa (D1) — claves de config
   * (camelCase, Anexo A) que difieren del nombre snake_case del campo de
   * la entidad (obj_camp/obj_plat/tipo_camp).
   */
  private const ETAPA_CONDITIONED_FIELDS = ['medio', 'objCamp', 'objPlat', 'tipoCamp'];

  /**
   * Listas planas editables vía Config Nivel 1/3 (`listEditorCard()` del
   * HTML de referencia) — `etapa`, `ubicacion`, `facultad` y `detalle` NO
   * se editan por este endpoint genérico (etapa es fija; ubicacion/
   * facultad se gestionan vía la matriz D4; detalle es texto libre).
   */
  private const EDITABLE_LISTS = ['segmento', 'campus', 'edad', 'senal', 'formato', 'nombre', 'motivo', 'mensaje', 'carrera', 'fecha'];

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

  // ==================================================================
  // Config Nivel 1/2/3 — mutaciones (§7.1/§11 Fase 4 del SDD)
  // ==================================================================

  /**
   * Agrega un valor a una lista plana editable.
   *
   * @throws ValidationException si $listKey no es editable por este endpoint.
   * @throws DuplicateNameException si el valor ya existe en la lista.
   */
  public function addListValue(string $listKey, string $value): void {
    $this->assertEditableList($listKey);
    $config = $this->configFactory->getEditable('utp_nomenclaturas.dictionary');
    $values = $config->get("lists.$listKey") ?? [];

    if (in_array($value, $values, TRUE)) {
      throw new DuplicateNameException('valor', $value, "la lista '$listKey'");
    }

    $values[] = $value;
    $config->set("lists.$listKey", array_values($values))->save();
  }

  /**
   * Quita un valor de una lista plana editable.
   *
   * @return bool TRUE si el valor existía y se quitó; FALSE si no existía
   *   (DELETE idempotente — el llamador decide si eso es 204 o 404).
   *
   * @throws ValidationException si $listKey no es editable por este endpoint.
   */
  public function deleteListValue(string $listKey, string $value): bool {
    $this->assertEditableList($listKey);
    $config = $this->configFactory->getEditable('utp_nomenclaturas.dictionary');
    $values = $config->get("lists.$listKey") ?? [];

    if (!in_array($value, $values, TRUE)) {
      return FALSE;
    }

    $config->set("lists.$listKey", array_values(array_filter($values, fn ($v) => $v !== $value)))->save();
    return TRUE;
  }

  /**
   * D1: reemplaza el array completo de opciones de un campo condicionado
   * por etapa — equivalente a editar los chips de una tarjeta de etapa en
   * Config Nivel 1 y guardar.
   *
   * @throws \InvalidArgumentException si $field no es uno de los 4 campos
   *   condicionados por etapa.
   */
  public function updateEtapaOptions(string $etapa, string $field, array $values): void {
    if (!in_array($field, self::ETAPA_CONDITIONED_FIELDS, TRUE)) {
      throw new \InvalidArgumentException(sprintf(
        "'%s' no es un campo condicionado por etapa. Válidos: %s",
        $field,
        implode(', ', self::ETAPA_CONDITIONED_FIELDS)
      ));
    }

    $config = $this->configFactory->getEditable('utp_nomenclaturas.dictionary');
    $config->set("etapa_conditionals.$field.$etapa", array_values($values))->save();
  }

  /**
   * D2: reemplaza el array completo de pilares de un segmento.
   */
  public function updateSegmentoPilar(string $segmento, array $pilares): void {
    $config = $this->configFactory->getEditable('utp_nomenclaturas.dictionary');
    $config->set("segmento_pilar.$segmento", array_values($pilares))->save();
  }

  /**
   * D4: reemplaza la matriz Campus×Facultad completa. Normaliza igual que
   * `toggleFacultadAtSede()` del HTML: si una facultad queda ofrecida en
   * TODAS las `sedes_especificas`, se elimina su entrada (pasa a "sin
   * restricción") — el servidor es la fuente de verdad de esta regla, no
   * el cliente.
   *
   * @param array<string, string[]> $matrix facultad => sedes permitidas.
   */
  public function updateCampusFacultad(array $matrix): void {
    $bundle = $this->getBundle();
    $sedesEspecificas = $bundle['sedes_especificas'] ?? [];

    $normalized = [];
    foreach ($matrix as $facultad => $sedes) {
      $sedes = array_values(array_unique($sedes));
      $coversAll = !array_diff($sedesEspecificas, $sedes);
      if (!$coversAll) {
        $normalized[$facultad] = $sedes;
      }
    }

    $config = $this->configFactory->getEditable('utp_nomenclaturas.dictionary');
    $config->set('campus_facultad', $normalized)->save();
  }

  private function assertEditableList(string $listKey): void {
    if (!in_array($listKey, self::EDITABLE_LISTS, TRUE)) {
      throw new ValidationException(
        "'$listKey' no es una lista editable.",
        [['field' => 'list_key', 'reason' => "Válidas: " . implode(', ', self::EDITABLE_LISTS)]]
      );
    }
  }

}
