<?php

namespace Drupal\utp_nomenclaturas\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Anuncio — hoja del árbol Campaña ▸ Conjunto ▸ Anuncio (§2.1 del SDD).
 */
#[ContentEntityType(
  id: 'ad',
  label: new TranslatableMarkup('Ad'),
  label_collection: new TranslatableMarkup('Ads'),
  label_singular: new TranslatableMarkup('ad'),
  label_plural: new TranslatableMarkup('ads'),
  base_table: 'ad',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'name',
  ],
  handlers: [
    'access' => 'Drupal\Core\Entity\EntityAccessControlHandler',
  ],
)]
class Ad extends ContentEntityBase {

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
    // eliminar Conjunto → sus anuncios).
    $fields['ad_set_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Conjunto de Anuncios'))
      ->setSetting('target_type', 'ad_set')
      ->setRequired(TRUE)
      ->setOnDelete(BaseFieldDefinition::ON_DELETE_CASCADE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nombre'))
      ->setDescription(new TranslatableMarkup('Nombre derivado (slug), snapshot inmutable — ADR-002.'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    // Campos snapshot que componen el nombre (§2.2/§3.1 del SDD), en orden.
    $fields['formato'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Formato'))
      ->setSetting('max_length', 64);

    $fields['concepto'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Concepto/Nombre'))
      ->setSetting('max_length', 128);

    $fields['motivo'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Motivo'))
      ->setSetting('max_length', 64);

    // `mensaje` admite valor libre además de la lista (§2.2 del SDD).
    $fields['mensaje'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Mensaje'))
      ->setSetting('max_length', 255);

    $fields['carrera'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Carrera'))
      ->setSetting('max_length', 64);

    $fields['fecha'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Fecha'))
      ->setSetting('max_length', 16);

    // NO forma parte del nombre (§2.2 del SDD): destino natural, se hereda
    // al siguiente anuncio en el frontend, no en el backend.
    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('URL de destino'))
      ->setSetting('max_length', 1024);

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
