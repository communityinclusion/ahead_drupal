<?php

namespace Drupal\feeds\Plugin\Type\Target;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;

/**
 * Interface for configurable target plugins.
 */
interface ConfigurableTargetInterface extends ConfigurableInterface, DependentPluginInterface {

  /**
   * Returns the summary for a target.
   *
   * @return string
   *   The configuration summary.
   */
  public function getSummary();

}
