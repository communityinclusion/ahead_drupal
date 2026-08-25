<?php

namespace Drupal\feeds\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;
use Drupal\feeds\FeedsItemInterface;

/**
 * Plugin implementation of the 'feeds_item' field type.
 *
 * @property int $imported
 * @property string $url
 * @property string $guid
 * @property string $hash
 */
#[FieldType(
  id: 'feeds_item',
  label: new TranslatableMarkup('Feed'),
  description: new TranslatableMarkup('Feeds import metadata.'),
  default_formatter: 'feeds_item_url',
  no_ui: TRUE,
  list_class: FeedsItemList::class,
)]
class FeedsItem extends EntityReferenceItem implements FeedsItemInterface {

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return Url::fromUri($this->url);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && is_array($values) && isset($values['url']) && empty($values['url'])) {
      // Set url explicitly to NULL to prevent validation errors.
      $values['url'] = NULL;
    }
    return parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return ['target_type' => 'feeds_feed'] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['imported'] = DataDefinition::create('timestamp')
      ->setLabel(t('Timestamp'));

    $properties['url'] = DataDefinition::create('uri')
      ->setLabel(t('Item URL'));

    $properties['guid'] = DataDefinition::create('string')
      ->setLabel(t('Item GUID'));

    $properties['hash'] = DataDefinition::create('string')
      ->setLabel(t('Item hash'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the target feed.',
          'type' => 'int',
          'not null' => TRUE,
          'unsigned' => TRUE,
        ],
        'imported' => [
          'type' => 'int',
          'not null' => TRUE,
          'unsigned' => TRUE,
          'description' => 'Import date of the feed item, as a Unix timestamp.',
        ],
        'url' => [
          'type' => 'text',
          'description' => 'Link to the feed item.',
        ],
        'guid' => [
          'type' => 'text',
          'description' => 'Unique identifier for the feed item.',
        ],
        'hash' => [
          'type' => 'varchar',
          // The length of an md5 hash.
          'length' => 32,
          'not null' => TRUE,
          'description' => 'The hash of the feed item.',
          'is_ascii' => TRUE,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
        'lookup_url' => ['target_id', ['url', 128]],
        'lookup_guid' => ['target_id', ['guid', 128]],
        'imported' => ['imported'],
        // There are queries that lookup the GUID over all feeds importers
        // (example:
        // \Drupal\feeds\Plugin\Type\Target\FieldTargetBase::getUniqueValue()).
        // With a lot of feeds items this can become a performance problem, so
        // we need an index over the GUID alone.
        'guid' => [['guid', 128]],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'feeds_feed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if (is_string($this->url)) {
      // Only trim if url is a string.
      $this->url = trim($this->url);
    }
    if (is_string($this->guid)) {
      $this->guid = trim($this->guid);
    }
    $this->imported = (int) $this->imported;
  }

}
