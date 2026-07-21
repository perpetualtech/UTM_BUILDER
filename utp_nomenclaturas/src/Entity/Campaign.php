<?php

namespace Drupal\utp_nomenclaturas\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Campaña — raíz del árbol Campaña ▸ Conjunto ▸ Anuncio (§2.1/§6.2 del SDD).
 *
 * Los campos de código (segmento, etapa, campus, medio, obj_camp, obj_plat,
 * tipo_camp) son snapshots inmutables (ADR-002): strings planos, sin
 * entity_reference hacia el diccionario, para que un nombre histórico nunca
 * cambie aunque el diccionario se edite después.
 */
#[ContentEntityType(
  id: 'campaign',
  label: new TranslatableMarkup('Campaign'),
  label_collection: new TranslatableMarkup('Campaigns'),
  label_singular: new TranslatableMarkup('campaign'),
  label_plural: new TranslatableMarkup('campaigns'),
  base_table: 'campaign',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
  ],
  handlers: [
    'access' => 'Drupal\Core\Entity\EntityAccessControlHandler',
  ],
)]
class Campaign extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setReadOnly(TRUE);

    $fields['pillar_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Pilar'))
      ->setDescription(new TranslatableMarkup('Dimensión de campaña (§2.1 del SDD), no es entidad propia.'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nombre'))
      ->setDescription(new TranslatableMarkup('Nombre derivado (slug), snapshot inmutable — ADR-002.'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    // Campos snapshot que componen el nombre (§2.2/§3.1 del SDD), en orden.
    $snapshot_fields = [
      'segmento' => new TranslatableMarkup('Segmento'),
      'etapa' => new TranslatableMarkup('Etapa'),
      'campus' => new TranslatableMarkup('Campus'),
      'medio' => new TranslatableMarkup('Medio'),
      'obj_camp' => new TranslatableMarkup('Objetivo de campaña'),
      'obj_plat' => new TranslatableMarkup('Objetivo de plataforma'),
      'tipo_camp' => new TranslatableMarkup('Tipo de campaña'),
    ];
    foreach ($snapshot_fields as $field_name => $label) {
      $fields[$field_name] = BaseFieldDefinition::create('string')
        ->setLabel($label)
        ->setSetting('max_length', 64);
    }

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Creado por'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Creado'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Modificado'));

    return $fields;
  }

}
