<?php

namespace Drupal\feeds\Annotation;

/**
 * Defines a Plugin annotation object for Feeds processor plugins.
 *
 * Plugin Namespace: Feeds\Processor.
 *
 * For a working example, see \Drupal\feeds\Feeds\Processor\EntityProcessor.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Processor\ProcessorInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class FeedsProcessor extends FeedsBase {

  /**
   * Form classes for the plugin.
   *
   * Possible keys:
   * - "configuration": Displayed when configuring the feed type.
   * - "feed": Displayed on the feed add/edit form.
   * - "option": A small form that appears on the plugin select box when
   *   configuring the feed type. Used by entity processor plugins to display
   *   a form for selecting an entity bundle.
   * Each class must implement \Drupal\Core\Plugin\PluginFormInterface.
   * If the form class requires dependency injection, it should implement
   * \Drupal\Core\DependencyInjection\ContainerInjectionInterface with a
   * create() method. The plugin will be set via
   * \Drupal\feeds\Plugin\PluginAwareInterface::setPlugin() if that
   * interface is implemented.
   *
   * @var array
   */
  public $form = [];

}
