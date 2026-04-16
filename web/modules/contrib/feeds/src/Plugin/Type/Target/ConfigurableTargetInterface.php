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
   * The summary is displayed in the feed type mapping form to show the current
   * configuration of the target plugin. Returning the summary as an array is
   * encouraged. The allowance of returning a string only exists for backwards
   * compatibility.
   *
   * @return string|array<string|\Drupal\Component\Render\MarkupInterface|array>
   *   The configuration summary. Can be:
   *   - A string (for backwards compatibility)
   *   - An array where each element is a string, MarkupInterface object, or
   *     render array. Render arrays are particularly useful for displaying
   *     warning messages with custom HTML markup.
   */
  public function getSummary();

}
