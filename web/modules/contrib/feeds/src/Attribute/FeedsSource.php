<?php

namespace Drupal\feeds\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FeedsSource attribute object.
 *
 * Plugin Namespace: Feeds\Source.
 *
 * For a working example, see \Drupal\feeds\Feeds\Source\BasicFieldSource.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Source\SourceInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FeedsSource extends Plugin {

  /**
   * Constructs a FeedsSource attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $title
   *   (optional) The plugin title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   (optional) The plugin description.
   * @param array|null $field_types
   *   (optional) The field types a source plugin applies to.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $category
   *   (optional) The category to which the source plugin belongs.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string|null $title = NULL,
    public readonly TranslatableMarkup|string|null $description = NULL,
    public readonly ?array $field_types = NULL,
    public readonly TranslatableMarkup|string|null $category = NULL,
  ) {}

}
