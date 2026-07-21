<?php

namespace Drupal\utp_nomenclaturas\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Conjunto de Anuncios — hijo de Campaign, padre de Ad (§2.1 del SDD).
 */
#[ContentEntityType(
  id: 'ad_set',
  label: new TranslatableMarkup('Ad Set'),
  label_collection: new TranslatableMarkup('Ad Sets'),
  label_singular: new TranslatableMarkup('ad set'),
  label_plural: new TranslatableMarkup('ad sets'),
  base_table: 'ad_set',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
  ],
  handlers: [
    'access' => 'Drupal\Core\Entity\EntityAccessControlHandler',
  ],
)]
class AdSet extends ContentEntityBase {

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

    // Relación real padre→hijo, con cascada de borrado (§3.3 del SDD:
    // eliminar Campaña → sus conjuntos y anuncios).
    $fields['campaign_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Campaña'))
      ->setSetting('target_type', 'campaign')
      ->setRequired(TRUE)
      ->setOnDelete(BaseFieldDefinition::ON_DELETE_CASCADE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nombre'))
      ->setDescription(new TranslatableMarkup('Nombre derivado (slug), snapshot inmutable — ADR-002.'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    // Campos snapshot que componen el nombre (§2.2/§3.1 del SDD), en orden.
    $snapshot_fields = [
      'edad' => new TranslatableMarkup('Edad'),
      'ubicacion' => new TranslatableMarkup('Ubicación'),
      'facultad' => new TranslatableMarkup('Facultad'),
      'senal' => new TranslatableMarkup('Señal'),
    ];
    foreach ($snapshot_fields as $field_name => $label) {
      $fields[$field_name] = BaseFieldDefinition::create('string')
        ->setLabel($label)
        ->setSetting('max_length', 64);
    }

    // `detalle` admite valor libre además de la lista (§2.2 del SDD).
    $fields['detalle'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Detalle'))
      ->setSetting('max_length', 255);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Peso'))
      ->setDefaultValue(0);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Creado'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Modificado'));

    return $fields;
  }

}
