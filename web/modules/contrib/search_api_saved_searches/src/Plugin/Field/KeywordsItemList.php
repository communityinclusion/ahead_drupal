<?php

namespace Drupal\search_api_saved_searches\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\search_api_saved_searches\SavedSearchInterface;

/**
 * Provides an item list class for the computed "search_keywords" field.
 */
class KeywordsItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $search = $this->getEntity();
    if (!($search instanceof SavedSearchInterface)) {
      return;
    }
    $query = $search->getQuery();
    if (!$query || !is_string($query->getOriginalKeys())) {
      return;
    }
    // @todo Might want to add cache metadata here, but would need a new custom
    //   data type.
    // @see \Drupal\entity_test\Plugin\Field\ComputedTestCacheableStringItemList
    // @see \Drupal\entity_test\Plugin\DataType\ComputedTestCacheableString
    $this->list[0] = $this->createItem(0, $query->getOriginalKeys());
  }

}
