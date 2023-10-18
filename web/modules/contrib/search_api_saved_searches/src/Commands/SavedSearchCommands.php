<?php

namespace Drupal\search_api_saved_searches\Commands;

use Drupal\search_api_saved_searches\Service\NewResultsCheck;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for search_api_saved_searches.
 */
class SavedSearchCommands extends DrushCommands {

  /**
   * The service for checking saved searches for new results.
   *
   * @var \Drupal\search_api_saved_searches\Service\NewResultsCheck
   */
  protected $newResultsCheck;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\search_api_saved_searches\Service\NewResultsCheck $new_results_check
   *   The service for checking saved searches for new results.
   */
  public function __construct(NewResultsCheck $new_results_check) {
    parent::__construct();

    $this->newResultsCheck = $new_results_check;
  }

  /**
   * Check all saved searches with expired notification intervals for new results.
   *
   * @param string|null $type_id
   *   (optional) The type of saved searches to check, or NULL to check searches
   *   for all enabled types that have at least one notification plugin set.
   *
   * @command search-api-saved-searches:check-all
   *
   * @usage search-api-saved-searches:check-all
   *   Checks all saved searches with expired notification intervals for new
   *   results.
   * @usage search-api-saved-searches:check-all default
   *   Checks all saved searches of type "default" with expired notification
   *   intervals for new results.
   *
   * @aliases sapi-ss-ca,saved-searches-check-all
   */
  public function checkAll(?string $type_id = NULL): void {
    $count = $this->newResultsCheck->checkAll($type_id);
    if ($count) {
      $this->logger->success(dt('Successfully checked @count saved searches for new results.', ['@count' => $count]));
    }
    else {
      $this->logger->notice(dt('No saved searches to check at the moment.'));
    }
  }

}
