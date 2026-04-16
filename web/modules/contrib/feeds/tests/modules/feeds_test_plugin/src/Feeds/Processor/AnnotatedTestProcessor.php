<?php

namespace Drupal\feeds_test_plugin\Feeds\Processor;

use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Item\ItemInterface;
use Drupal\feeds\Feeds\Processor\ProcessorBase;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\feeds\StateInterface;

/**
 * Defines a test processor using annotations.
 *
 * @FeedsProcessor(
 *   id = "annotated_test_processor",
 *   title = @Translation("Annotated Test Processor"),
 *   description = @Translation("A test processor plugin using annotations."),
 * )
 */
class AnnotatedTestProcessor extends ProcessorBase implements ProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function process(FeedInterface $feed, ItemInterface $item, StateInterface $state) {
    // No-op for testing.
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel() {
    return 'Test item';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabelPlural() {
    return 'Test items';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount(FeedInterface $feed) {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportedItemIds(FeedInterface $feed) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiredIds(FeedInterface $feed, $time = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function expireItem(FeedInterface $feed, $item_id, StateInterface $state) {
    // No-op for testing.
  }

  /**
   * {@inheritdoc}
   */
  public function expiryTime() {
    return ProcessorInterface::EXPIRE_NEVER;
  }

}
