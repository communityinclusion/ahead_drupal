<?php

namespace Drupal\feeds_test_plugin\Feeds\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsSource;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Feeds\Item\ItemInterface;
use Drupal\feeds\Plugin\Type\Source\SourceBase;

/**
 * Defines a test source using attributes.
 */
#[FeedsSource(
  id: 'attribute_test_source',
  title: new TranslatableMarkup('Attribute Test Source'),
  description: new TranslatableMarkup('A test source plugin using attributes.'),
  field_types: ['text'],
  category: new TranslatableMarkup('Test'),
)]
class AttributeTestSource extends SourceBase {

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
