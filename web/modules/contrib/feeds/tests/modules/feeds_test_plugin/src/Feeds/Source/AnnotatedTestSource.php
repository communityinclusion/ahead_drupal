<?php

namespace Drupal\feeds_test_plugin\Feeds\Source;

use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Feeds\Item\ItemInterface;
use Drupal\feeds\Plugin\Type\Source\SourceBase;

/**
 * Defines a test source using annotations.
 *
 * @FeedsSource(
 *   id = "annotated_test_source",
 *   title = @Translation("Annotated Test Source"),
 *   description = @Translation("A test source plugin using annotations."),
 *   field_types = {"text"},
 *   category = @Translation("Test"),
 * )
 */
class AnnotatedTestSource extends SourceBase {

  /**
   * {@inheritdoc}
   */
  public static function sources(array &$sources, FeedTypeInterface $feed_type, array $definition) {
    $sources['test_source'] = [
      'label' => t('Test Source'),
      'description' => t('A test source.'),
      'id' => $definition['id'],
      'type' => (string) $definition['category'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceElement(FeedInterface $feed, ItemInterface $item) {
    return [];
  }

}
