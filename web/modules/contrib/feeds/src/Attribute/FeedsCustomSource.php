<?php

namespace Drupal\feeds\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FeedsCustomSource attribute object.
 *
 * Custom sources are user defined source fields. They are mostly used by
 * parsers that don't provide predefined source fields.
 *
 * Plugin Namespace: Feeds\CustomSource.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\CustomSource\CustomSourceInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FeedsCustomSource extends Plugin {

  /**
   * Constructs a FeedsCustomSource attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The plugin title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   (optional) The plugin description.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string $title,
    public readonly TranslatableMarkup|string|null $description = NULL,
  ) {}

}
