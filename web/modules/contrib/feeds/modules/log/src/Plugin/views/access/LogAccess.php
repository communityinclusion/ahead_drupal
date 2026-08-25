<?php

namespace Drupal\feeds_log\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsAccess;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that checks access for logs of a single feed.
 *
 * @ingroup views_access_plugins
 */
#[ViewsAccess(
  id: 'feeds_log_access',
  title: new TranslatableMarkup('Feeds Log access'),
  help: new TranslatableMarkup('Checks if the current user may view the logs of the given feed.'),
)]
class LogAccess extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Feeds Log access');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('feeds_log.access');
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', 'feeds_log.access_handler::access');
    $route->setOption('parameters', [
      'feeds_feed' => [
        'type' => 'entity:feeds_feed',
      ],
    ]);
  }

}
