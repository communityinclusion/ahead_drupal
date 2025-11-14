<?php

declare(strict_types = 1);

namespace Drupal\search_api_saved_searches_test\Plugin\search_api_saved_searches\notification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_saved_searches\Attribute\SearchApiSavedSearchesNotification;
use Drupal\search_api_saved_searches\Notification\NotificationPluginBase;
use Drupal\search_api_saved_searches\SavedSearchInterface;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a notification plugin to use in tests.
 */
#[SearchApiSavedSearchesNotification(
  id: 'search_api_saved_searches_test',
  label: new TranslatableMarkup('Test plugin'),
)]
class TestNotificationPlugin extends NotificationPluginBase {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return $this->configuration['dependencies'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      unset($this->configuration['dependencies']);
    }
    return $remove;
  }

  /**
   * {@inheritdoc}
   */
  public function notify(SavedSearchInterface $search, ResultSetInterface $results): void {
  }

}
