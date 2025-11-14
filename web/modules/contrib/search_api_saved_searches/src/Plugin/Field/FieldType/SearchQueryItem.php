<?php

namespace Drupal\search_api_saved_searches\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines a field item type for serialized properties.
 */
#[FieldType(
  id: 'search_api_saved_searches_query',
  label: new TranslatableMarkup('Test serialized field item'),
  description: new TranslatableMarkup('A field containing a serialized string value.'),
  category: 'general',
)]
class SearchQueryItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('search_api_saved_searches_query')
      ->setLabel(new TranslatableMarkup('Search query'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

}
