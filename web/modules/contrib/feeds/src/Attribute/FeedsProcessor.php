<?php

namespace Drupal\feeds\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FeedsProcessor attribute object.
 *
 * Plugin Namespace: Feeds\Processor.
 *
 * For a working example, see \Drupal\feeds\Feeds\Processor\EntityProcessor.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Processor\ProcessorInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FeedsProcessor extends Plugin {

  /**
   * Constructs a FeedsProcessor attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $title
   *   (optional) The plugin title. May be omitted when a deriver supplies it.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   (optional) The plugin description.
   * @param string|null $entity_type
   *   (optional) The entity type ID this processor creates or updates.
   * @param array $form
   *   (optional) Form classes for the plugin. Possible keys:
   *   - "configuration": Displayed when configuring the feed type.
   *   - "feed": Displayed on the feed add/edit form.
   *   - "option": A small form that appears on the plugin select box when
   *     configuring the feed type. Used by entity processor plugins to display
   *     a form for selecting an entity bundle.
   *   Each class must implement \Drupal\Core\Plugin\PluginFormInterface. If the
   *   form class requires dependency injection, it should implement
   *   \Drupal\Core\DependencyInjection\ContainerInjectionInterface with a
   *   create() method. The plugin will be set via
   *   \Drupal\feeds\Plugin\PluginAwareInterface::setPlugin() if that interface
   *   is implemented.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string|null $title = NULL,
    public readonly TranslatableMarkup|string|null $description = NULL,
    public readonly ?string $entity_type = NULL,
    public readonly array $form = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
