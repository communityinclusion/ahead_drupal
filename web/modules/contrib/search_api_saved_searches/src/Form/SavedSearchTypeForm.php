<?php

namespace Drupal\search_api_saved_searches\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Display\DisplayPluginManager;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api_saved_searches\Notification\NotificationPluginManagerInterface;
use Drupal\search_api_saved_searches\SavedSearchesException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for adding and editing saved search types.
 */
class SavedSearchTypeForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\search_api_saved_searches\SavedSearchTypeInterface
   */
  protected $entity;

  /**
   * The notification plugin manager.
   *
   * @var \Drupal\search_api_saved_searches\Notification\NotificationPluginManagerInterface|null
   */
  protected $notificationPluginManager;

  /**
   * The display plugin manager.
   *
   * @var \Drupal\search_api\Display\DisplayPluginManager|null
   */
  protected $displayPluginManager;

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelperInterface|null
   */
  protected $dataTypeHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $form = parent::create($container);

    $form->setStringTranslation($container->get('string_translation'));
    $form->setEntityTypeManager($container->get('entity_type.manager'));
    $form->setNotificationPluginManager($container->get('plugin.manager.search_api_saved_searches.notification'));
    $form->setDisplayPluginManager($container->get('plugin.manager.search_api.display'));
    $form->setDataTypeHelper($container->get('search_api.data_type_helper'));

    return $form;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * Retrieves the notification plugin manager.
   *
   * @return \Drupal\search_api_saved_searches\Notification\NotificationPluginManagerInterface
   *   The notification plugin manager.
   */
  public function getNotificationPluginManager(): NotificationPluginManagerInterface {
    return $this->notificationPluginManager ?: \Drupal::service('plugin.manager.search_api_saved_searches.notification');
  }

  /**
   * Sets the notification plugin manager.
   *
   * @param \Drupal\search_api_saved_searches\Notification\NotificationPluginManagerInterface $notification_plugin_manager
   *   The new notification plugin manager.
   *
   * @return $this
   */
  public function setNotificationPluginManager(NotificationPluginManagerInterface $notification_plugin_manager): self {
    $this->notificationPluginManager = $notification_plugin_manager;
    return $this;
  }

  /**
   * Retrieves the display plugin manager.
   *
   * @return \Drupal\search_api\Display\DisplayPluginManager
   *   The display plugin manager.
   */
  public function getDisplayPluginManager(): DisplayPluginManager {
    return $this->displayPluginManager ?: \Drupal::service('plugin.manager.search_api.display');
  }

  /**
   * Sets the display plugin manager.
   *
   * @param \Drupal\search_api\Display\DisplayPluginManager $display_plugin_manager
   *   The new display plugin manager.
   *
   * @return $this
   */
  public function setDisplayPluginManager(DisplayPluginManager $display_plugin_manager): self {
    $this->displayPluginManager = $display_plugin_manager;
    return $this;
  }

  /**
   * Retrieves the data type helper.
   *
   * @return \Drupal\search_api\Utility\DataTypeHelperInterface
   *   The data type helper.
   */
  public function getDataTypeHelper(): DataTypeHelperInterface {
    return $this->dataTypeHelper ?: \Drupal::service('search_api.data_type_helper');
  }

  /**
   * Sets the data type helper.
   *
   * @param \Drupal\search_api\Utility\DataTypeHelperInterface $data_type_helper
   *   The new data type helper.
   *
   * @return $this
   */
  public function setDataTypeHelper(DataTypeHelperInterface $data_type_helper): self {
    $this->dataTypeHelper = $data_type_helper;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $type = $this->entity;
    $form['#tree'] = TRUE;
    if ($type->isNew()) {
      $form['#title'] = $this->t('Create saved search type');
    }
    else {
      $args = ['%type' => $type->label()];
      $form['#title'] = $this->t('Edit saved search type %type', $args);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type name'),
      '#description' => $this->t('Enter the displayed name for the saved search type.'),
      '#default_value' => $type->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $type->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => '\Drupal\search_api_saved_searches\Entity\SavedSearchType::load',
        'source' => ['label'],
      ],
      '#disabled' => !$type->isNew(),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('An optional description for this type. This will only be shown to administrators.'),
      '#default_value' => $type->getDescription(),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Disabling a saved search type will prevent the creation of new saved searches of that type and stop notifications for existing searches of that type.'),
      '#default_value' => $type->status(),
    ];

    $form['advanced'] = [
      '#type' => 'vertical_tabs',
    ];

    $display_options = [];
    $displays = $this->getDisplayPluginManager()->getInstances();
    foreach ($displays as $display_id => $display) {
      $display_options[$display_id] = $display->label();
    }
    $form['options']['displays'] = [
      '#type' => 'details',
      '#title' => $this->t('Search displays'),
      '#description' => $this->t('Select for which search displays saved searches of this type can be created.'),
      '#group' => 'advanced',
    ];
    if (count($display_options) > 0) {
      $form['options']['displays']['default'] = [
        '#type' => 'radios',
        '#options' => [
          1 => $this->t('For all displays except the selected'),
          0 => $this->t('Only for the selected displays'),
        ],
        '#default_value' => (int) $type->getOption('displays.default', TRUE),
      ];
      $form['options']['displays']['selected'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Search displays'),
        '#options' => $display_options,
        '#default_value' => $type->getOption('displays.selected', []),
      ];
    }
    else {
      $form['options']['displays']['default'] = [
        '#type' => 'radios',
        '#options' => [
          1 => $this->t('Applies to all displays by default'),
          0 => $this->t('Applies to no displays by default'),
        ],
        '#default_value' => (int) $type->getOption('displays.default', TRUE),
      ];
      $form['options']['displays']['selected'] = [
        '#type' => 'value',
        '#value' => [],
      ];
    }

    $form['notifications'] = [
      '#type' => 'details',
      '#title' => $this->t('Notifications'),
      '#group' => 'advanced',
    ];
    $form['notifications']['notification_plugins'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Notification method'),
      '#description' => $this->t('This determines how users will be notified of new results for their saved searches.'),
      '#default_value' => $type->getNotificationPluginIds(),
      '#ajax' => [
        'trigger_as' => ['name' => 'notification_plugins_configure'],
        'callback' => '::buildAjaxNotificationPluginConfigForm',
        'wrapper' => 'search-api-notification-plugins-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#parents' => ['notification_plugins'],
    ];
    $notification_plugin_options = [];
    try {
      foreach ($this->getNotificationPluginManager()->createPlugins($type) as $plugin_id => $notification_plugin) {
        $notification_plugin_options[$plugin_id] = $notification_plugin->label();
        $form['notifications']['notification_plugins'][$plugin_id]['#description'] = $notification_plugin->getDescription();
      }
    }
    catch (SavedSearchesException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $this->messenger()->addError($this->t('An error occurred loading the notification plugins: @message.', ['@message' => $e->getMessage()]));
    }
    asort($notification_plugin_options, SORT_NATURAL | SORT_FLAG_CASE);
    $form['notifications']['notification_plugins']['#options'] = $notification_plugin_options;

    $form['notifications']['notification_configs'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'search-api-notification-plugins-config-form',
      ],
      '#tree' => TRUE,
      '#parents' => ['notification_configs'],
    ];

    $form['notifications']['notification_plugin_configure_button'] = [
      '#type' => 'submit',
      '#name' => 'notification_plugins_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => [['notification_plugins']],
      '#submit' => ['::submitAjaxNotificationPluginConfigForm'],
      '#ajax' => [
        'callback' => '::buildAjaxNotificationPluginConfigForm',
        'wrapper' => 'search-api-notification-plugins-config-form',
      ],
      '#attributes' => ['class' => ['js-hide']],
      '#parents' => ['notification_plugin_configure_button'],
    ];

    $this->buildNotificationPluginConfigForm($form, $form_state);

    $form['notifications']['notify_interval'] = [
      '#type' => 'container',
      '#title' => $this->t('Notification interval'),
      '#parents' => ['options', 'notify_interval'],
    ];
    $form['notifications']['notify_interval']['default_value'] = [
      '#type' => 'number',
      '#title' => $this->t('Default notification interval'),
      '#description' => $this->t('The default notification interval, in seconds, to set for saved searches created for this type. Set to -1 to never send notifications.'),
      '#default_value' => $type->getOption('notify_interval.default_value', 86400),
    ];
    $form['notifications']['notify_interval']['customizable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let the user change the notification interval'),
      '#default_value' => $type->getOption('notify_interval.customizable', TRUE),
    ];
    $default_interval_options = [
      3600 => t('Hourly'),
      86400 => t('Daily'),
      604800 => t('Weekly'),
      -1 => t('Never'),
    ];
    $options_value = $type->getOption('notify_interval.options', $default_interval_options);
    // Usually, the options value will already be a parsed options list.
    // However, during AJAX form submits it can actually already be a string, as
    // entered by the user. In that case, simply use it as-is.
    if (is_array($options_value)) {
      $interval_options = [];
      foreach ($options_value as $k => $v) {
        $interval_options[] = "$k | $v";
      }
      $interval_options = implode("\n", $interval_options);
    }
    else {
      $interval_options = $options_value;
    }
    $form['notifications']['notify_interval']['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Interval options'),
      '#description' => $this->t('The possible intervals the user can choose from, in seconds. Enter one value per line, in the format seconds|label. Use a negative value for disabling notifications.'),
      '#default_value' => $interval_options,
      '#states' => [
        'visible' => [
          ':input[name="notify_interval[customizable]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['misc'] = [
      '#type' => 'details',
      '#title' => $this->t('Miscellaneous'),
      '#group' => 'advanced',
    ];
    $date_field_title = $this->t('Method for determining new results');
    $form['misc']['date_field'] = [
      '#type' => 'fieldset',
      '#title' => $date_field_title,
      '#description' => $this->t('The method by which to decide which results are new. "Determine by result ID" will internally save the IDs of all results that were previously found by the user and only report results not already reported. (This might use a lot of memory for large result sets.) The other options check whether the date in the selected field is later than the date of last notification.'),
    ];
    $form['misc']['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of new results to report'),
      '#description' => $this->t('Set this to a value greater than 0 to cap the number of new results reported in a single notification at that number.'),
      '#min' => 1,
      '#default_value' => $type->getOption('max_results', ''),
      '#parents' => ['options', 'max_results'],
    ];
    $determine_by_result_id = $this->t('Determine by result ID');
    $vars = [
      '@determine_by_result_id' => $determine_by_result_id,
      '@method_for_determining_new_results' => $date_field_title,
    ];
    $form['misc']['query_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit to set on the search query'),
      '#description' => $this->t('If "@determine_by_result_id" is selected as the "@method_for_determining_new_results" below, then more results than are shown to the user need to be retrieved from the search server in order to determine the new ones. To avoid overloading the search server, encountering "out of memory" exceptions or similar, you can set a query limit here. Do note, however, that this means that not all new results might be reported in some cases, or even none at all.', $vars),
      '#min' => 1,
      '#default_value' => $type->getOption('query_limit', ''),
      '#parents' => ['options', 'query_limit'],
      '#states' => [
        // Will be set below to only be visible if at least one index is set to
        // "Determine by result ID". (Cannot use "visible" here since that
        // doesn't work reliably with "or".)
        'invisible' => [],
      ],
    ];
    $form['misc']['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('User interface description'),
      '#description' => $this->t('Enter a text that will be displayed to users when creating a saved search. You can use HTML in this field.'),
      '#default_value' => $type->getOption('description', ''),
      '#parents' => ['options', 'description'],
    ];

    // Populate the actual options for "date_field", along with the #states for
    // "query_limit".
    try {
      /** @var \Drupal\search_api\IndexInterface[] $indexes */
      $indexes = $this->getEntityTypeManager()
        ->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (PluginException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $indexes = [];
    }
    $data_type_helper = $this->getDataTypeHelper();
    foreach ($indexes as $index_id => $index) {
      $fields = [];
      foreach ($index->getFields() as $key => $field) {
        // We misuse isTextType() here to check for the "Date" type instead.
        if ($data_type_helper->isTextType($field->getType(), ['date'])) {
          $fields[$key] = $this->t('Determine by @name', ['@name' => $field->getLabel()]);
        }
      }
      $fields = [NULL => $determine_by_result_id] + $fields;
      $form['misc']['date_field'][$index_id] = [
        '#type' => 'select',
        '#title' => count($indexes) === 1 ? NULL : $this->t('Searches on index %index', ['%index' => $index->label()]),
        '#options' => $fields,
        '#disabled' => count($fields) === 1,
        '#default_value' => $type->getOption("date_field.$index_id"),
        '#parents' => ['options', 'date_field', $index_id],
      ];

      // Add a condition for the "query_limit" state, to hide it if all indexes
      // have a date field (and not "Determine by result ID") selected.
      $form['misc']['query_limit']['#states']['invisible'] += [
        ':input[name="options[date_field][' . $index_id . ']"]' => [
          '!value' => '',
        ],
      ];
    }

    return $form;
  }

  /**
   * Builds the configuration forms for all selected notification plugins.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buildNotificationPluginConfigForm(array &$form, FormStateInterface $form_state): void {
    $type = $this->entity;

    $selected_plugins = $form_state->getValue('notification_plugins');
    if ($selected_plugins === NULL) {
      // Initial form build, use the saved notification plugins (or none for new
      // indexes).
      $plugins = $type->getNotificationPlugins();
    }
    else {
      // The form is being rebuilt – use the notification plugins selected by
      // the user instead of the ones saved in the config.
      try {
        $plugins = $this->getNotificationPluginManager()
          ->createPlugins($type, $selected_plugins);
      }
      catch (SavedSearchesException $e) {
        watchdog_exception('search_api_saved_searches', $e);
        $this->messenger()->addError($this->t('An error occurred loading the notification plugins: @message.', ['@message' => $e->getMessage()]));
        return;
      }
    }
    $form_state->set('notification_plugins', array_keys($plugins));

    $show_message = FALSE;
    foreach ($plugins as $plugin_id => $plugin) {
      if ($plugin instanceof PluginFormInterface) {
        // Get the "sub-form state" and appropriate form part to send to
        // buildConfigurationForm().
        $plugin_form = [];
        if (!empty($form['notifications']['notification_configs'][$plugin_id])) {
          $plugin_form = $form['notifications']['notification_configs'][$plugin_id];
        }
        $plugin_form_state = SubformState::createForSubform($plugin_form, $form, $form_state);
        $form['notifications']['notification_configs'][$plugin_id] = $plugin->buildConfigurationForm($plugin_form, $plugin_form_state);

        $show_message = TRUE;
        $form['notifications']['notification_configs'][$plugin_id]['#type'] = 'details';
        $form['notifications']['notification_configs'][$plugin_id]['#title'] = $this->t('Configure the %notification notification method', ['%notification' => $plugin->label()]);
        $form['notifications']['notification_configs'][$plugin_id]['#open'] = $type->isNew();
      }
    }

    // If the user changed the notification plugins and there is at least one
    // plugin config form, show a message telling the user to configure it.
    if ($selected_plugins && $show_message) {
      $message = $this->t('Please configure the used notification methods.');
      $this->messenger()->addWarning($message);
    }
  }

  /**
   * Handles changes to the selected notification plugins.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The part of the form to return as AJAX.
   *
   * @noinspection PhpUnusedParameterInspection
   */
  public function buildAjaxNotificationPluginConfigForm(array $form, FormStateInterface $form_state): array {
    return $form['notifications']['notification_configs'];
  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected notification plugins.
   *
   * @param array $form
   *   The current form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @noinspection PhpUnusedParameterInspection
   */
  public function submitAjaxNotificationPluginConfigForm(array $form, FormStateInterface $form_state): void {
    $form_state->setValue('id', NULL);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\search_api_saved_searches\SavedSearchTypeInterface $type */
    $type = $this->getEntity();

    // Store the selected displays as a numerically indexed array.
    $key = ['options', 'displays', 'selected'];
    $selected = $form_state->getValue($key, []);
    $selected = array_keys(array_filter($selected));
    $form_state->setValue($key, $selected);

    // Store the array of notification plugin IDs with integer keys.
    $plugin_ids = array_values(array_filter($form_state->getValue('notification_plugins', [])));
    $form_state->setValue('notification_plugins', $plugin_ids);

    // Make sure "query_limit" is not less than the "max_results", if both are
    // set.
    $max_results = $form_state->getValue(['options', 'max_results']);
    $query_limit = $form_state->getValue(['options', 'query_limit']);
    if ($max_results && $query_limit && $max_results > $query_limit) {
      $vars = [
        '@query_limit' => $form['misc']['query_limit']['#title'],
        '@max_results' => $form['misc']['max_results']['#title'],
      ];
      $form_state->setErrorByName('options][query_limit', $this->t('"@query_limit" must not be less than "@max_results", if both are specified.', $vars));
    }
    else {
      if ($max_results === '') {
        $form_state->unsetValue(['options', 'max_results']);
      }
      if ($query_limit === '') {
        $form_state->unsetValue(['options', 'query_limit']);
      }
    }

    // Parse and validate the "Notification interval" options.
    $notify_interval = $form_state->getValue(['options', 'notify_interval']);
    $notify_interval['customizable'] = (bool) $notify_interval['customizable'];
    $notify_interval['default_value'] = (int) $notify_interval['default_value'];
    $options_text = $notify_interval['options'] ?? '';
    $options_text = trim($options_text);
    $notify_interval['options'] = [];
    if ($options_text) {
      foreach (explode("\n", $options_text) as $line) {
        if (!trim($line)) {
          continue;
        }
        $parts = explode('|', $line, 2);
        if (count($parts) == 1) {
          $k = $v = trim($line);
        }
        else {
          [$k, $v] = array_map('trim', $parts);
        }
        $notify_interval['options'][$k] = $v;
      }
    }
    elseif (!empty($notify_interval['customizable'])) {
      $vars = [
        '@customizable' => $form['notifications']['notify_interval']['customizable']['#title'],
        '@options' => $form['notifications']['notify_interval']['options']['#title'],
      ];
      $form_state->setError($form['notifications']['notify_interval']['options'], $this->t('"@options" must be specified if "@customizable" is enabled.', $vars));
    }
    $form_state->setValue(['options', 'notify_interval'], $notify_interval);

    // Call validateConfigurationForm() for each enabled notification plugin
    // with a form.
    try {
      $plugins = $this->getNotificationPluginManager()
        ->createPlugins($type, $plugin_ids);
    }
    catch (SavedSearchesException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $error = $this->t('An error occurred loading the notification plugins: @message.', ['@message' => $e->getMessage()]);
      $form_state->setError($form['notification_plugins'], $error);
      return;
    }
    $previous_plugins = $form_state->get('notification_plugins');
    foreach ($plugins as $plugin_id => $plugin) {
      if ($plugin instanceof PluginFormInterface) {
        if (!in_array($plugin_id, $previous_plugins)) {
          $form_state->setRebuild();
          continue;
        }
        $plugin_form = &$form['notifications']['notification_configs'][$plugin_id];
        $plugin_form_state = SubformState::createForSubform($plugin_form, $form, $form_state);
        $plugin->validateConfigurationForm($plugin_form, $plugin_form_state);
      }
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\search_api_saved_searches\SavedSearchesException
   *   Thrown if the plugins could not be loaded.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\search_api_saved_searches\SavedSearchTypeInterface $type */
    $type = $this->getEntity();

    $plugin_ids = $form_state->getValue('notification_plugins', []);
    $plugins = $this->getNotificationPluginManager()
      ->createPlugins($type, $plugin_ids);
    foreach ($plugins as $plugin_id => $plugin) {
      if ($plugin instanceof PluginFormInterface) {
        $plugin_form_state = SubformState::createForSubform($form['notifications']['notification_configs'][$plugin_id], $form, $form_state);
        $plugin->submitConfigurationForm($form['notifications']['notification_configs'][$plugin_id], $plugin_form_state);
      }
    }
    $type->setNotificationPlugins($plugins);

    if ($this->entity->isNew()) {
      $form_state->setRedirect('entity.search_api_saved_search_type.edit_form', [
        'search_api_saved_search_type' => $type->id(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $return = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('Your settings have been saved.'));

    return $return;
  }

}
