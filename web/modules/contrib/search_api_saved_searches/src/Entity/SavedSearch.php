<?php

namespace Drupal\search_api_saved_searches\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Site\Settings;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_saved_searches\Plugin\Field\KeywordsItemList;
use Drupal\search_api_saved_searches\SavedSearchesException;
use Drupal\search_api_saved_searches\SavedSearchInterface;
use Drupal\search_api_saved_searches\SavedSearchTypeInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Provides an entity type for saved searches.
 *
 * @ContentEntityType(
 *   id = "search_api_saved_search",
 *   label = @Translation("Saved search"),
 *   label_collection = @Translation("Saved searches"),
 *   label_singular = @Translation("saved search"),
 *   label_plural = @Translation("saved searches"),
 *   label_count = @PluralTranslation(
 *     singular = "@count saved search",
 *     plural = "@count saved searches"
 *   ),
 *   bundle_label = @Translation("Search type"),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\search_api_saved_searches\Entity\SavedSearchAccessControlHandler",
 *     "views_data" = "Drupal\search_api_saved_searches\SavedSearchViewsData",
 *     "form" = {
 *       "default" = "Drupal\search_api_saved_searches\Form\SavedSearchForm",
 *       "create" = "Drupal\search_api_saved_searches\Form\SavedSearchCreateForm",
 *       "edit" = "Drupal\search_api_saved_searches\Form\SavedSearchForm",
 *       "delete" = "Drupal\search_api_saved_searches\Form\SavedSearchDeleteConfirmForm",
 *     },
 *   },
 *   admin_permission = "administer search_api_saved_searches",
 *   base_table = "search_api_saved_search",
 *   data_table = "search_api_saved_search",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "label",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *   },
 *   bundle_entity_type = "search_api_saved_search_type",
 *   field_ui_base_route = "entity.search_api_saved_search_type.edit_form",
 *   token_type = "search-api-saved-search",
 *   permission_granularity = "bundle",
 *   links = {
 *     "canonical" = "/saved-search/{search_api_saved_search}",
 *     "activate" = "/saved-search/{search_api_saved_search}/activate",
 *     "edit-form" = "/saved-search/{search_api_saved_search}/edit",
 *     "delete-form" = "/saved-search/{search_api_saved_search}/delete",
 *   },
 * )
 */
class SavedSearch extends ContentEntityBase implements SavedSearchInterface {

  use EntityOwnerTrait;

  /**
   * Static cache for property getters that take some computation.
   *
   * @var array
   */
  protected $cachedProperties = [];

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    try {
      $fields += static::ownerBaseFieldDefinitions($entity_type);
    }
    catch (UnsupportedEntityTypeDefinitionException $e) {
      watchdog_exception('search_api_saved_searches', $e);
    }

    // Make the form display of the language configurable, and provide a more
    // sensible default value.
    $fields['langcode']->setDisplayConfigurable('form', TRUE)
      ->setDefaultValueCallback(__CLASS__ . '::getCurrentLanguage');

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The label for the saved search.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid']
      ->setLabel(t('Created by'))
      ->setDescription(t('The user who owns the saved search.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Activated'))
      ->setDescription(t('Whether the saved search has been activated.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'weight' => 0,
        'settings' => [
          'on_label' => t('Activated'),
          'off_label' => t('Activation pending'),
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setDescription(t('The time that the saved search was created.'))
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['last_executed'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Last executed'))
      ->setDescription(t('The time that the saved search was last checked for new results.'))
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['next_execution'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Next execution'))
      ->setDescription(t('The next time this saved search should be executed.'))
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['notify_interval'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Notification interval'))
      ->setDescription(t('The interval in which you want to receive notifications of new results for this saved search.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        3600 => t('Hourly'),
        86400 => t('Daily'),
        604800 => t('Weekly'),
        -1 => t('Never'),
      ])
      ->setDefaultValue(-1)
      ->setDisplayOptions('view', [
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['index_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Index ID'))
      ->setSetting('max_length', 50);

    $fields['query'] = BaseFieldDefinition::create('search_api_saved_searches_query')
      ->setLabel(t('Search query'))
      ->setDescription(t('The saved search query.'))
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ]);

    $fields['search_keywords'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Fulltext keywords'))
      ->setDescription(t('The fulltext keywords set on this search.'))
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setInternal(FALSE)
      ->setClass(KeywordsItemList::class)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['path'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Path'))
      ->setDescription(t("The path to this saved search's query."))
      ->setSetting('case_sensitive', TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    /** @var \Drupal\search_api_saved_searches\SavedSearchTypeInterface $type */
    try {
      $type = \Drupal::entityTypeManager()
        ->getStorage('search_api_saved_search_type')
        ->load($bundle);
      // If we are called with an illegal $bundle, avoid a fatal error.
      if ($type) {
        $fields += $type->getNotificationPluginFieldDefinitions();
      }
    }
    catch (PluginException $e) {
      watchdog_exception('search_api_saved_searches', $e);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);

    // Clean up and clone the search query before it gets serialized.
    if (isset($values['query']) && $values['query'] instanceof QueryInterface) {
      $query = $values['query'];

      // Remember the executed query so we can avoid re-executing it in this
      // page request to get the known results.
      $values['cachedProperties']['executed query'] = $query;

      // Get the original, unexecuted query and clone it to not mess with the
      // original.
      $values['query'] = static::getCleanQueryForStorage($query);
    }
    else {
      unset($values['query']);
    }
  }

  /**
   * Cleans up a search query prior to storing it in a saved search.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   A cleaned-up copy of the search query.
   */
  protected static function getCleanQueryForStorage(QueryInterface $query): QueryInterface {
    // Clone the query to not mess with the original.
    $query = clone $query;

    // Search queries created via Views will have a
    // \Drupal\views\ViewExecutable object in the "search_api_view" option
    // as possibly useful metadata for alter hooks, etc. The big problem
    // with that is that those objects will automatically re-execute the
    // view when they are unserialized, which is a huge, completely
    // unnecessary overhead in our case (which might furthermore confuse
    // modules reacting to searches, like Facets – or this one). It's hard
    // to tell what a "proper" solution for this problem would look like,
    // but probably just unsetting this option in the query we save will
    // work well enough in almost all cases.
    $options = &$query->getOptions();
    unset($options['search_api_view']);
    unset($options);
    $original_query = $query->getOriginalQuery();
    $original_query_options = &$original_query->getOptions();
    unset($original_query_options['search_api_view']);
    unset($original_query_options);

    // Reset the result set to its original state (except for warnings and
    // ignored keywords which will usually be set during pre-execute).
    $results = $query->getResults();
    $results->setResultCount(0);
    $results->setResultItems([]);
    $data = &$results->getAllExtraData();
    $data = [];
    unset($data);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);

    // The "cachedProperties" values set in preCreate() above will end up in
    // $this->value['cachedProperties'] by default. It's probably easiest to
    // just let that happen and move the values to the property here.
    if (isset($this->values['cachedProperties'])) {
      foreach ($this->values['cachedProperties'] as $key => $value) {
        $this->cachedProperties[$key] = $value;
      }
      unset($this->values['cachedProperties']);
    }

    // Set a default label for new saved searches. (Can't use a "default value
    // callback" for the label field because the query only gets set afterwards,
    // based on the order of field definitions.)
    if (empty($this->get('label')->value)) {
      $label = NULL;
      $query = $this->getQuery();
      if ($query && is_string($query->getOriginalKeys())) {
        $label = $query->getOriginalKeys();
      }
      $this->set('label', $label ?: t('Saved search'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Update the "next_execution" field, if notifications are enabled.
    $notify_interval = $this->get('notify_interval')->value;
    if ($notify_interval >= 0) {
      $last_executed = $this->get('last_executed')->value;
      $this->set('next_execution', $last_executed + $notify_interval);
    }
    else {
      $this->set('next_execution', NULL);
    }

    // Set the "index_id" field, if necessary.
    if ($this->isNew() && !$this->get('index_id')->value) {
      $query = $this->getQuery();
      if ($query) {
        $this->set('index_id', $query->getIndex()->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // For newly inserted saved searches with "Determine by result ID" detection
    // mode, prime the list of known results.
    if (!$update) {
      try {
        $type = $this->getType();
      }
      catch (SavedSearchesException $e) {
        return;
      }
      $query = $this->getQuery();
      if (!$query) {
        return;
      }
      $index_id = $query->getIndex()->id();
      $date_field = $type->getOption("date_field.$index_id");
      if ($date_field) {
        return;
      }
      // Prime the "search_api_saved_searches_old_results" table with entries
      // for all current results. If we already have the executed version of
      // the query, we use that so we don't need to execute it again.
      $new_results_check = \Drupal::getContainer()
        ->get('search_api_saved_searches.new_results_check');
      if (!empty($this->cachedProperties['executed query'])) {
        /** @var \Drupal\search_api\Query\QueryInterface $query */
        $query = $this->cachedProperties['executed query'];
        $items = $query->getResults()->getResultItems();
        $new_results_check->saveKnownResults($this, $items);
      }
      else {
        try {
          // Otherwise, just use the "new results check" code, which will
          // automatically save all results it encounters.
          $new_results_check->getNewResults($this);
        }
        catch (SavedSearchesException $e) {
          watchdog_exception('search_api_saved_searches', $e);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Remove any "known results" we have for the deleted searches.
    // NB: $entities is not documented to be keyed by entity ID, but since Core
    // relies on it (see \Drupal\comment\Entity\Comment::postDelete()), we
    // should be able to do the same.
    \Drupal::database()
      ->delete('search_api_saved_searches_old_results')
      ->condition('search_id', array_keys($entities), 'IN')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($name): void {
    parent::onChange($name);

    // Keywords are a computed field, but we allow editing. If an edit happens,
    // just store the resulting keywords back in the query.
    if ($name === 'search_keywords') {
      $keys = $this->__get($name)->__get('value');
      $this->getQuery()->keys($keys);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel): array {
    $params = parent::urlRouteParameters($rel);

    // Since Drupal is still not able to reproduce field values in their correct
    // data types, we cast to string to get a correct check even for ""/NULL.
    if ($rel === 'activate' || (string) $this->getOwnerId() === '0') {
      $operations = [
        'canonical' => 'view',
        'activate' => 'activate',
        'edit-form' => 'edit',
        'delete-form' => 'delete',
      ];
      if (isset($operations[$rel])) {
        $params['token'] = $this->getAccessToken($operations[$rel]);
      }
    }

    return $params;
  }

  /**
   * Returns the default value for the "langcode" base field.
   *
   * @return array
   *   An array with the default value.
   *
   * @see \Drupal\search_api_saved_searches\Entity\SavedSearch::baseFieldDefinitions()
   */
  public static function getCurrentLanguage(): array {
    return [\Drupal::languageManager()->getCurrentLanguage()->getId()];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): SavedSearchTypeInterface {
    if (!isset($this->cachedProperties['type'])) {
      try {
        $type = \Drupal::entityTypeManager()
          ->getStorage('search_api_saved_search_type')
          ->load($this->bundle());
      }
      catch (PluginException $e) {
        watchdog_exception('search_api_saved_searches', $e);
      }
      $this->cachedProperties['type'] = $type ?? FALSE;
    }

    if (!$this->cachedProperties['type']) {
      throw new SavedSearchesException("Saved search #{$this->id()} does not have a valid type set");
    }
    return $this->cachedProperties['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): ?string {
    return $this->get('langcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(): ?QueryInterface {
    return $this->get('query')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuery(QueryInterface $query): SavedSearchInterface {
    $this->set('query', $query);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): ?string {
    return $this->get('path')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken($operation): string {
    $key = $this->getEntityTypeId() . ':' . $this->id() . ':' . $operation;
    return Crypt::hmacBase64($key, Settings::getHashSalt());
  }

}
