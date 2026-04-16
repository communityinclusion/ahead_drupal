<?php

namespace Drupal\search_api_page_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Throws an exception during search query execution when triggered by state.
 */
class QueryExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new QueryExceptionSubscriber.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'onQueryPreExecute',
    ];
  }

  /**
   * Throws an exception if the test state flag is set.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query pre-execute event.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event) {
    if ($this->state->get('search_api_page_test.throw_exception')) {
      throw new SearchApiException('Simulated backend failure.');
    }
  }

}
