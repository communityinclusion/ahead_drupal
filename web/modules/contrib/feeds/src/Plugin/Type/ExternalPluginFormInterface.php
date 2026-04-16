<?php

namespace Drupal\feeds\Plugin\Type;

use Drupal\Core\Plugin\PluginFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for Feeds plugins that have an external form.
 *
 * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
 *   Form classes should implement \Drupal\Core\Plugin\PluginFormInterface
 *   instead. For dependency injection, implement
 *   \Drupal\Core\DependencyInjection\ContainerInjectionInterface with a
 *   create() method. The plugin will be set via
 *   \Drupal\feeds\Plugin\PluginAwareInterface::setPlugin() if that
 *   interface is implemented.
 *
 * @see https://www.drupal.org/node/3564251
 */
interface ExternalPluginFormInterface extends PluginFormInterface {

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param \Drupal\feeds\Plugin\Type\FeedsPluginInterface $plugin
   *   The plugin.
   *
   * @return static
   *   Returns an instance of this plugin form.
   */
  public static function create(ContainerInterface $container, FeedsPluginInterface $plugin);

}
