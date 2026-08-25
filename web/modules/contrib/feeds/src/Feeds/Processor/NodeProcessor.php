<?php

namespace Drupal\feeds\Feeds\Processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsProcessor;
use Drupal\feeds\Feeds\Processor\Form\DefaultEntityProcessorForm;
use Drupal\feeds\Feeds\Processor\Form\EntityProcessorOptionForm;

/**
 * Defines a node processor.
 *
 * Creates nodes from feed items.
 */
#[FeedsProcessor(
  id: 'entity:node',
  title: new TranslatableMarkup('Node'),
  description: new TranslatableMarkup('Creates nodes from feed items.'),
  entity_type: 'node',
  form: [
    'configuration' => DefaultEntityProcessorForm::class,
    'option' => EntityProcessorOptionForm::class,
  ]
)]
class NodeProcessor extends EntityProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function entityLabel() {
    return $this->t('Node');
  }

  /**
   * {@inheritdoc}
   */
  public function entityLabelPlural() {
    return $this->t('Nodes');
  }

}
