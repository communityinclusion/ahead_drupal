<?php

namespace Drupal\feeds\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceIdFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'feeds_item_target_id' formatter.
 */
#[FieldFormatter(
  id: 'feeds_item_target_id',
  label: new TranslatableMarkup('Feed ID'),
  description: new TranslatableMarkup('Display the ID of the feed entity.'),
  field_types: [
    'feeds_item',
  ]
)]
class FeedsItemTargetIdFormatter extends EntityReferenceIdFormatter {}
