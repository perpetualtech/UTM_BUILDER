<?php

namespace Drupal\utp_nomenclaturas\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * UTM manual — módulo lateral para tráfico sin pauta (§2.1 del SDD),
 * no acoplado al árbol Campaña ▸ Conjunto ▸ Anuncio.
 */
#[ContentEntityType(
  id: 'manual_utm',
  label: new TranslatableMarkup('Manual UTM'),
  label_collection: new TranslatableMarkup('Manual UTMs'),
  label_singular: new TranslatableMarkup('manual UTM'),
  label_plural: new TranslatableMarkup('manual UTMs'),
  base_table: 'manual_utm',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'access' => 'Drupal\Core\Entity\EntityAccessControlHandler',
  ],
)]
class ManualUtm extends ContentEntityBase {

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

    // §3.3 del SDD: utm_source y utm_medium obligatorios.
    $fields['utm_source'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('utm_source'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['utm_medium'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('utm_medium'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['utm_campaign'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('utm_campaign'))
      ->setSetting('max_length', 255);

    $fields['utm_term'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('utm_term'))
      ->setSetting('max_length', 255);

    $fields['utm_content'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('utm_content'))
      ->setSetting('max_length', 255);

    // URL final completa (para bio/enlace del influencer) — §3.4 del SDD.
    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('URL'))
      ->setSetting('max_length', 1024)
      ->setRequired(TRUE);

    $fields['qs'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Query string'))
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Creado por'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Creado'));

    return $fields;
  }

}
