<?php

namespace Drupal\feeds;

/**
 * Provides an interface defining methods related to scheduling.
 */
interface ScheduledFeedInterface {

  /**
   * Checks if a feed is scheduled to run.
   *
   * @return bool
   *   True if it is scheduled, false otherwise.
   */
  public function isScheduled(): bool;

}
