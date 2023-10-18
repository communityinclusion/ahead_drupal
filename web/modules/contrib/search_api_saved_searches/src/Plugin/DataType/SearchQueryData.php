<?php

namespace Drupal\search_api_saved_searches\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;
use Drupal\search_api\Query\QueryInterface;

/**
 * Provides a data type wrapping a Search API query.
 *
 * @DataType(
 *   id = "search_api_saved_searches_query",
 *   label = @Translation("Search query"),
 *   description = @Translation("A search query"),
 * )
 */
class SearchQueryData extends TypedData {

  /**
   * The search query.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    if ($value && !$value instanceof QueryInterface) {
      throw new \InvalidArgumentException("Value assigned to \"{$this->getName()}\" is not a valid search query");
    }
    parent::setValue($value, $notify);
  }

}
