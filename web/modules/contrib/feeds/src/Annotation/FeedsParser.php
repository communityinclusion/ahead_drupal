<?php

namespace Drupal\feeds\Annotation;

/**
 * Defines a Plugin annotation object for Feeds parser plugins.
 *
 * Plugin Namespace: Feeds\Parser.
 *
 * For a working example, see \Drupal\feeds\Feeds\Parser\SyndicationParser.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Parser\ParserInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class FeedsParser extends FeedsBase {

  /**
   * Form classes for the plugin.
   *
   * Possible keys:
   * - "configuration": Displayed when configuring the feed type.
   * - "feed": Displayed on the feed add/edit form.
   * - "option": A small form that appears on the plugin select box when
   *   configuring the feed type.
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
