<?php

namespace Drupal\feeds_test_plugin\Feeds\CustomSource;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsCustomSource;
use Drupal\feeds\Feeds\CustomSource\BlankSource;

/**
 * Defines a test custom source using attributes.
 */
#[FeedsCustomSource(
  id: 'attribute_test_custom_source',
  title: new TranslatableMarkup('Attribute Test Custom Source'),
  description: new TranslatableMarkup('A test custom source plugin using attributes.'),
)]
class AttributeTestCustomSource extends BlankSource {}
