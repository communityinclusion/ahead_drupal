<?php

namespace Drupal\feeds_test_plugin\Feeds\Fetcher;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsFetcher;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\Fetcher\FetcherInterface;
use Drupal\feeds\Plugin\Type\PluginBase;
use Drupal\feeds\Result\FetcherResult;
use Drupal\feeds\StateInterface;

/**
 * Defines a test fetcher using attributes.
 */
#[FeedsFetcher(
  id: 'attribute_test_fetcher',
  title: new TranslatableMarkup('Attribute Test Fetcher'),
  description: new TranslatableMarkup('A test fetcher plugin using attributes.'),
)]
class AttributeTestFetcher extends PluginBase implements FetcherInterface {

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
    return ['source' => 'test://attribute'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

}
