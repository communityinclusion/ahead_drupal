<?php

namespace Drupal\feeds\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a FeedsTarget attribute object.
 *
 * Plugin Namespace: Feeds\Target.
 *
 * For a working example, see \Drupal\feeds\Feeds\Target\Text.
 *
 * @see \Drupal\feeds\Plugin\Type\FeedsPluginManager
 * @see \Drupal\feeds\Plugin\Type\Target\TargetInterface
 * @see \Drupal\feeds\Plugin\Type\PluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class FeedsTarget extends Plugin {

  /**
   * Constructs a FeedsTarget attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $title
   *   (optional) The plugin title.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string|null $description
   *   (optional) The plugin description.
   * @param array|null $field_types
   *   (optional) The field types a target plugin applies to.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string|null $title = NULL,
    public readonly TranslatableMarkup|string|null $description = NULL,
    public readonly ?array $field_types = NULL,
  ) {}

}
