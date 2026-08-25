<?php

namespace Drupal\feeds\Feeds\Target;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\Attribute\FeedsTarget;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedsItemInterface;
use Drupal\feeds\FeedsItemListInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a feeds_item field mapper.
 */
#[FeedsTarget(
  id: 'feeds_item',
  field_types: ['feeds_item'],
)]
class FeedsItem extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('url')
      ->addProperty('guid')
      ->markPropertyUnique('url')
      ->markPropertyUnique('guid');
  }

  /**
   * {@inheritdoc}
   *
   * Merges mapped columns into the feeds_item row for the active feed only.
   * The feeds_item field could contain references to multiple feeds, we leave
   * items that reference other feeds untouched.
   *
   * In case the source passes several value rows, the values are merged to a
   * single row. The first row wins on duplicate keys, later rows only add
   * missing columns. For example:
   *
   * @code
   * // Input ($values after prepareValues()), two deltas from one source item:
   * [
   *   0 => ['guid' => 'alpha', 'url' => 'http://example.com/one'],
   *   1 => ['guid' => 'beta', 'hash' => '9f86d081884c7d659a2'],
   * ];
   *
   * // Resulting $mapped used to update this feed's row:
   * [
   *   'guid' => 'alpha',
   *   'url' => 'http://example.com/one',
   *   'hash' => '9f86d081884c7d659a2',
   * ];
   * @endcode
   */
  public function setTarget(FeedInterface $feed, EntityInterface $entity, $field_name, array $values) {
    $values = $this->prepareValues($values);
    if ($values === []) {
      return;
    }

    $entity_target = $this->getEntityTarget($feed, $entity);
    if (!$entity_target instanceof EntityInterface) {
      throw new \RuntimeException(sprintf('No entity target found for feed %s.', $feed->id()));
    }

    $item_list = $entity_target->get($field_name);
    if (!$item_list instanceof FeedsItemListInterface) {
      throw new \RuntimeException(sprintf('The feeds_item mapper requires a field list implementing %s; %s given.', FeedsItemListInterface::class, get_class($item_list)));
    }

    // Get the item for current feed.
    $item = $item_list->getItemByFeed($feed);
    if (!$item instanceof FeedsItemInterface) {
      // In case none of the items reference the current feed yet, initialize a
      // new one. This is an edge case, because by default the entity processor
      // sets a reference to the feed on the feeds item field before applying
      // mappings.
      $item = $item_list->getItemByFeed($feed, TRUE);
    }
    if (!$item instanceof FeedsItemInterface) {
      throw new \RuntimeException(sprintf('Could not get or create a feeds_item row for feed %s.', $feed->id()));
    }

    // One source item can provide multiple value rows, but for the feed_item
    // field we only want to update a single item. In case there are multiple
    // value rows, combine the values of all rows to one single row. For each
    // column we pick the first occurrence.
    $mapped = [];
    foreach ($values as $columns) {
      $mapped = $mapped + $columns;
    }

    // Set the values on the feeds_item field.
    foreach ($mapped as $property => $value) {
      $item->{$property} = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    if (isset($values['url']) && empty($values['url'])) {
      // If 'url' is empty, set it explicitly to NULL to prevent validation
      // errors.
      $values['url'] = NULL;
    }
  }

}
