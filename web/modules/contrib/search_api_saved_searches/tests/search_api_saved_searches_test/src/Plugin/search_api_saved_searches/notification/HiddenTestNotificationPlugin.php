<?php

declare(strict_types = 1);

namespace Drupal\search_api_saved_searches_test\Plugin\search_api_saved_searches\notification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api_saved_searches\Attribute\SearchApiSavedSearchesNotification;

/**
 * Provides a hidden notification plugin to use in tests.
 */
#[SearchApiSavedSearchesNotification(
  id: 'search_api_saved_searches_test_hidden',
  label: new TranslatableMarkup('Hidden plugin'),
)]
class HiddenTestNotificationPlugin extends TestNotificationPlugin {
}
