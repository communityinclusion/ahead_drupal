<?php

namespace Drupal\feeds\Feeds\Processor;

use Drupal\feeds\Attribute\FeedsProcessor;
use Drupal\feeds\Feeds\Processor\Form\DefaultEntityProcessorForm;
use Drupal\feeds\Feeds\Processor\Form\EntityProcessorOptionForm;
use Drupal\feeds\Plugin\Derivative\GenericContentEntityProcessor as GenericContentEntityProcessorDeriver;

/**
 * Provides a generic content entity processor.
 */
#[FeedsProcessor(
  id: 'entity',
  form: [
    'configuration' => DefaultEntityProcessorForm::class,
    'option' => EntityProcessorOptionForm::class,
  ],
  deriver: GenericContentEntityProcessorDeriver::class
)]
class GenericContentEntityProcessor extends EntityProcessorBase {

}
