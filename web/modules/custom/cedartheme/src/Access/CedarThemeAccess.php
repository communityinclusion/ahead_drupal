<?php

namespace Drupal\cedartheme\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access handler for the Hello World route.
 */
class CedarThemeAccess implements AccessInterface {

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CedarThemeAccess constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Handles the access checking.
   *
   * @param AccountInterface $account
   *
   * @return AccessResult
   */
  public function access(AccountInterface $account) {
    $config = $this->configFactory->get('cedartheme.custom_salutation');
    $salutation = $config->get('salutation');
    $access = in_array('editor', $account->getRoles()) && $salutation != "" ? AccessResult::forbidden() : AccessResult::allowed();
    $access->addCacheableDependency($config);
    $access->addCacheableDependency($account);
    return $access;
  }
}
