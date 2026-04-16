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
 * Defines a test parser using attributes.
 */
#[FeedsParser(
  id: 'attribute_test_parser',
  title: new TranslatableMarkup('Attribute Test Parser'),
  description: new TranslatableMarkup('A test parser plugin using attributes.'),
)]
class AttributeTestParser extends ParserBase implements ParserInterface {

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

}
