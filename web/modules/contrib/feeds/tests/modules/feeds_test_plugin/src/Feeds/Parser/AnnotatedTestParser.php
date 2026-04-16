<?php

namespace Drupal\feeds_test_plugin\Feeds\Parser;

use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Parser\ParserBase;
use Drupal\feeds\Plugin\Type\Parser\ParserInterface;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;

/**
 * Defines a test parser using annotations.
 *
 * @FeedsParser(
 *   id = "annotated_test_parser",
 *   title = @Translation("Annotated Test Parser"),
 *   description = @Translation("A test parser plugin using annotations."),
 * )
 */
class AnnotatedTestParser extends ParserBase implements ParserInterface {

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
