<?php

namespace Drupal\utp_nomenclaturas\Service;

/**
 * Deriva nombres de Campaign/AdSet/Ad — §3.1 del SDD.
 *
 * Servicio puro: no depende de config ni de la base de datos, recibe
 * arrays de campos ya resueltos por el llamador (TreeManager). Esto lo
 * hace testeable con `Drupal\Tests\UnitTestCase`, sin bootstrap de Drupal.
 */
class NameBuilder {

  /**
   * Fallback de acentos latinos comunes, usado solo si la extensión `intl`
   * (función `normalizer_normalize()`) no está disponible en el entorno.
   */
  private const ACCENT_FALLBACK = [
    'á' => 'a', 'Á' => 'A', 'é' => 'e', 'É' => 'E',
    'í' => 'i', 'Í' => 'I', 'ó' => 'o', 'Ó' => 'O',
    'ú' => 'u', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N',
    'ü' => 'u', 'Ü' => 'U', 'à' => 'a', 'À' => 'A',
    'è' => 'e', 'È' => 'E', 'ì' => 'i', 'Ì' => 'I',
    'ò' => 'o', 'Ò' => 'O', 'ù' => 'u', 'Ù' => 'U',
    'ä' => 'a', 'Ä' => 'A', 'ë' => 'e', 'Ë' => 'E',
    'ï' => 'i', 'Ï' => 'I', 'ö' => 'o', 'Ö' => 'O',
    'ç' => 'c', 'Ç' => 'C',
  ];

  /**
   * slug(v) — §3.1: trim, NFD + strip de diacríticos, espacios → "-".
   *
   * NO fuerza minúsculas (los valores del diccionario ya vienen normalizados).
   */
  public function slug(?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
      return '';
    }

    if (\function_exists('normalizer_normalize')) {
      $normalized = normalizer_normalize($value, \Normalizer::FORM_D);
      $value = preg_replace('/\p{Mn}/u', '', $normalized ?? $value);
    }
    else {
      $value = strtr($value, self::ACCENT_FALLBACK);
    }

    return preg_replace('/\s+/', '-', $value);
  }

  /**
   * Nombre derivado de Campaign: los 7 campos snapshot + pillar_code al
   * final (§3.1 y §2.2 del SDD — el pilar se agrega al final).
   *
   * @param array $fields Claves: segmento, etapa, campus, medio, obj_camp,
   *   obj_plat, tipo_camp, pillar_code (obligatorio).
   *
   * @throws \InvalidArgumentException si falta pillar_code.
   */
  public function campaignName(array $fields): string {
    if (empty($fields['pillar_code'])) {
      throw new \InvalidArgumentException('campaignName() requiere pillar_code.');
    }

    $order = ['segmento', 'etapa', 'campus', 'medio', 'obj_camp', 'obj_plat', 'tipo_camp'];
    $segments = $this->slugOrderedFields($fields, $order);
    $segments[] = $this->slug($fields['pillar_code']);

    return implode('_', $segments);
  }

  /**
   * Nombre derivado de AdSet — §3.1: edad, ubicacion, facultad, senal, detalle.
   */
  public function adSetName(array $fields): string {
    $order = ['edad', 'ubicacion', 'facultad', 'senal', 'detalle'];
    return implode('_', $this->slugOrderedFields($fields, $order));
  }

  /**
   * Nombre derivado de Ad — §3.1: formato, concepto, motivo, mensaje, carrera, fecha.
   */
  public function adName(array $fields): string {
    $order = ['formato', 'concepto', 'motivo', 'mensaje', 'carrera', 'fecha'];
    return implode('_', $this->slugOrderedFields($fields, $order));
  }

  /**
   * Aplica slug() a los campos en el orden dado, descartando los vacíos.
   *
   * @return string[]
   */
  private function slugOrderedFields(array $fields, array $order): array {
    $segments = [];
    foreach ($order as $field) {
      $slugged = $this->slug($fields[$field] ?? '');
      if ($slugged !== '') {
        $segments[] = $slugged;
      }
    }
    return $segments;
  }

}
