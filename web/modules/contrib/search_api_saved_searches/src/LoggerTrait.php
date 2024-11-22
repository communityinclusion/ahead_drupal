<?php

declare(strict_types = 1);

namespace Drupal\search_api_saved_searches;

use Drupal\search_api\LoggerTrait as SearchApiLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Provides helper methods for logging with dependency injection.
 */
trait LoggerTrait {

  use SearchApiLoggerTrait;

  /**
   * Retrieves the logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  public function getLogger(): LoggerInterface {
    return $this->logger ?: \Drupal::service('logger.channel.search_api_saved_searches');
  }

}
