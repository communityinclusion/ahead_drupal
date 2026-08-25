<?php

namespace Drupal\feeds_test_plugin\Feeds\Parser;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsParser;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Parser\ParserBase;
use Drupal\feeds\Plugin\Type\Parser\ParserInterface;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;

/**
 * Dummy parser to test integration providing custom source types.
 */
#[FeedsParser(
  id: 'parser_with_foo_source',
  title: 'Parser with Foo Source',
  description: new TranslatableMarkup("Parser supporting the Custom source type 'foo'.")
)]
class ParserWithFooSource extends ParserBase implements ParserInterface {

  /**
   * {@inheritdoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    return new ParserResult();
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingSources() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCustomSourcePlugins(): array {
    return ['foo'];
  }

}
