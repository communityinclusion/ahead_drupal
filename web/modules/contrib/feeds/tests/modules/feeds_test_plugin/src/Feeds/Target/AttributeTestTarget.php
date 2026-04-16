<?php

namespace Drupal\feeds_test_plugin\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsTarget;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a test target using attributes.
 */
#[FeedsTarget(
  id: 'attribute_test_target',
  title: new TranslatableMarkup('Attribute Test Target'),
  description: new TranslatableMarkup('A test target plugin using attributes.'),
  field_types: ['text'],
)]
class AttributeTestTarget extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value');
  }

}
