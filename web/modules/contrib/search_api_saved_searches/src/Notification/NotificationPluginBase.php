<?php

namespace Drupal\search_api_saved_searches\Notification;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\Plugin\ConfigurablePluginBase;
use Drupal\search_api_saved_searches\SavedSearchTypeInterface;

/**
 * Defines a base class for notification plugins.
 *
 * Plugins extending this class need to provide a plugin definition using the
 * SearchApiSavedSearchesNotification attribute:
 *
 * @code
 * #[SearchApiSavedSearchesNotification(
 *   id: 'my_notification',
 *   label: new TranslatableMarkup('My notification'),
 *   description: new TranslatableMarkup('This is my notification plugin.'),
 * )]
 * @endcode
 *
 * @see \Drupal\search_api_saved_searches\Attribute\SearchApiSavedSearchesNotification
 * @see \Drupal\search_api_saved_searches\Notification\DataTypePluginManager
 * @see \Drupal\search_api_saved_searches\Notification\NotificationPluginInterface
 * @see plugin_api
 */
abstract class NotificationPluginBase extends ConfigurablePluginBase implements NotificationPluginInterface {

  /**
   * The saved search type to which this plugin is attached.
   */
  protected ?SavedSearchTypeInterface $savedSearchType = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    if (!empty($configuration['#saved_search_type']) && $configuration['#saved_search_type'] instanceof SavedSearchTypeInterface) {
      $this->setSavedSearchType($configuration['#saved_search_type']);
      unset($configuration['#saved_search_type']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedSearchType(): SavedSearchTypeInterface {
    return $this->savedSearchType;
  }

  /**
   * {@inheritdoc}
   */
  public function setSavedSearchType(SavedSearchTypeInterface $type): NotificationPluginInterface {
    $this->savedSearchType = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFieldFormDisplay(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    return AccessResult::allowed();
  }

}
