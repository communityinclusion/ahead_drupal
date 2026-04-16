<?php

namespace Drupal\feeds\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Base annotation class for Feeds plugins.
 */
abstract class FeedsBase extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The title of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * An optional form class that is separate from the plugin.
   *
   * @var string
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   This property is not used. Form classes should be defined in the 'form'
   *   array instead, using the 'configuration' key. For example:
   *   @code
   *   form = {
   *     "configuration" = "Drupal\example\Form\ExampleForm",
   *   }
   *   @endcode
   *
   * @see https://www.drupal.org/node/3564253
   */
  public $configuration_form;

  /**
   * Constructor arguments.
   *
   * @var array
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   This property is not used.
   *
   * @see https://www.drupal.org/node/3564255
   */
  public $arguments;

}
