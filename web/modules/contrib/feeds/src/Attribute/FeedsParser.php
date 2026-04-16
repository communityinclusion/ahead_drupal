<?php

namespace Drupal\feeds\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FeedsParser attribute object.
 *
 * Plugin Namespace: Feeds\Parser.
 *
 * For a working example, see \Drupal\feeds\Feeds\Parser\SyndicationParser.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Parser\ParserInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FeedsParser extends Plugin {

  /**
   * Constructs a FeedsParser attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The plugin title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   (optional) The plugin description.
   * @param array $form
   *   (optional) Form classes for the plugin. Possible keys:
   *   - "configuration": Displayed when configuring the feed type.
   *   - "feed": Displayed on the feed add/edit form.
   *   - "option": A small form that appears on the plugin select box when
   *     configuring the feed type.
   *   Each class must implement \Drupal\Core\Plugin\PluginFormInterface. If the
   *   form class requires dependency injection, it should implement
   *   \Drupal\Core\DependencyInjection\ContainerInjectionInterface with a
   *   create() method. The plugin will be set via
   *   \Drupal\feeds\Plugin\PluginAwareInterface::setPlugin() if that interface
   *   is implemented.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string $title,
    public readonly TranslatableMarkup|string|null $description = NULL,
    public readonly array $form = [],
  ) {}

}
