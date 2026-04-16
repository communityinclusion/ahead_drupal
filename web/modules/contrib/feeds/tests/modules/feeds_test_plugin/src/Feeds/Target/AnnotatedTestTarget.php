<?php

namespace Drupal\feeds_test_plugin\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a test target using annotations.
 *
 * @FeedsTarget(
 *   id = "annotated_test_target",
 *   field_types = {"text"},
 * )
 */
class AnnotatedTestTarget extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('value');
  }

}
