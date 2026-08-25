<?php

namespace Drupal\feeds_test_plugin\Feeds\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsProcessor;
use Drupal\feeds\Feeds\Processor\EntityProcessorBase;
use Drupal\feeds\Feeds\Processor\Form\DefaultEntityProcessorForm;
use Drupal\feeds\Feeds\Processor\Form\EntityProcessorOptionForm;

/**
 * Defines an entity_test processor.
 */
#[FeedsProcessor(
  id: 'entity:entity_test',
  title: new TranslatableMarkup('Test entity overridden'),
  description: new TranslatableMarkup('Creates test entities from feed items.'),
  entity_type: 'entity_test',
  form: [
    'configuration' => DefaultEntityProcessorForm::class,
    'option' => EntityProcessorOptionForm::class,
  ]
)]
class EntityTestProcessor extends EntityProcessorBase {}
