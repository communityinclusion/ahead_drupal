<?php

namespace Drupal\feeds_test_plugin\Feeds\CustomSource;

use Drupal\feeds\Feeds\CustomSource\BlankSource;

/**
 * Defines a test custom source using annotations.
 *
 * @FeedsCustomSource(
 *   id = "annotated_test_custom_source",
 *   title = @Translation("Annotated Test Custom Source"),
 *   description = @Translation("A test custom source plugin using annotations."),
 * )
 */
class AnnotatedTestCustomSource extends BlankSource {}
