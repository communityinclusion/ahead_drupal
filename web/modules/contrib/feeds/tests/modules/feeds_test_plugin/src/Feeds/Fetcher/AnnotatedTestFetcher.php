<?php

namespace Drupal\feeds_test_plugin\Feeds\Fetcher;

use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\Fetcher\FetcherInterface;
use Drupal\feeds\Plugin\Type\PluginBase;
use Drupal\feeds\Result\FetcherResult;
use Drupal\feeds\StateInterface;

/**
 * Defines a test fetcher using annotations.
 *
 * @FeedsFetcher(
 *   id = "annotated_test_fetcher",
 *   title = @Translation("Annotated Test Fetcher"),
 *   description = @Translation("A test fetcher plugin using annotations."),
 * )
 */
class AnnotatedTestFetcher extends PluginBase implements FetcherInterface {

  /**
   * {@inheritdoc}
   */
  public function fetch(FeedInterface $feed, StateInterface $state) {
    // Return a simple test result.
    $source = $feed->getSource();
    if (empty($source)) {
      throw new EmptyFeedException();
    }
    return new FetcherResult($source);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultFeedConfiguration() {
    return ['source' => 'test://annotated'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
