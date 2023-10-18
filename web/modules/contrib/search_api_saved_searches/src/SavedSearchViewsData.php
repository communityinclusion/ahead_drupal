<?php

namespace Drupal\search_api_saved_searches;

use Drupal\views\EntityViewsData;

/**
 * Provides the Views data for the search_api_saved_search entity type.
 */
class SavedSearchViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['search_api_saved_search']['search_keywords'] = [
      'title' => $this->t('Fulltext keywords'),
      'field' => [
        'id' => 'field',
        'default_formatter' => 'string',
        'field_name' => 'search_keywords',
      ],
    ];

    return $data;

  }

}
