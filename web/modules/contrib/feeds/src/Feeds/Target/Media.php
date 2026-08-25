<?php

namespace Drupal\feeds\Feeds\Target;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Token;
use Drupal\feeds\Attribute\FeedsTarget;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\DownloadException;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\InvalidFileExtensionException;
use Drupal\feeds\Exception\ReferenceNotFoundException;
use Drupal\feeds\Exception\TargetValidationException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Processor\EntityProcessorInterface;
use Drupal\feeds\Utility\FileResolverInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\Entity\Media as MediaEntity;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a media field mapper.
 */
#[FeedsTarget(
  id: 'media',
  field_types: ['entity_reference'],
)]
class Media extends EntityReference {

  use FileExistsTrait;

  /**
   * Media name generation: use file name.
   */
  const FILE_NAME = 'file_name';

  /**
   * Media name generation: use entity name.
   */
  const ENTITY_NAME = 'entity_name';

  /**
   * The file resolver service.
   *
   * @var \Drupal\feeds\Utility\FileResolverInterface
   */
  protected $fileResolver;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Media-specific settings.
   *
   * @var array
   */
  protected $mediaSettings;

  /**
   * The current feed context.
   *
   * @var \Drupal\feeds\FeedInterface|null
   */
  protected $feedContext;

  /**
   * The current entity context.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $entityContext;

  /**
   * Constructs a Media object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\feeds\EntityFinderInterface $entity_finder
   *   The Feeds entity finder service.
   * @param \Drupal\feeds\Utility\FileResolverInterface $file_resolver
   *   The file resolver service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityFinderInterface $entity_finder, FileResolverInterface $file_resolver, Token $token, FileSystemInterface $file_system) {
    // Set mediaSettings before calling parent so it's available in
    // defaultConfiguration().
    $this->mediaSettings = $this->getMediaFieldsSettings($configuration);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $entity_finder);
    $this->fileResolver = $file_resolver;
    $this->token = $token;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('feeds.entity_finder'),
      $container->get('feeds.file_resolver'),
      $container->get('token'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function targets(array &$targets, FeedTypeInterface $feed_type, array $definition) {
    // Don't show mapping target to media if the module is not enabled.
    if (!\Drupal::moduleHandler()->moduleExists('media')) {
      return;
    }
    // If the file module is not enabled, we cannot perform checks for file
    // related classes.
    if (!\Drupal::moduleHandler()->moduleExists('file')) {
      return;
    }

    $processor = $feed_type->getProcessor();

    if (!$processor instanceof EntityProcessorInterface) {
      return $targets;
    }
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($processor->entityType(), $processor->bundle());

    // Only use this target if field is media and at least one of its bundles
    // has a source field that extends from FileItem.
    foreach ($field_definitions as $id => $field_definition) {
      if ($field_definition->getType() == 'entity_reference' && $field_definition->getSetting('target_type') == 'media') {
        $media_field_settings = $field_definition->getSettings();
        if (!isset($media_field_settings['handler_settings']['target_bundles']) || !is_array($media_field_settings['handler_settings']['target_bundles'])) {
          continue;
        }
        foreach ($media_field_settings['handler_settings']['target_bundles'] as $media_type_id) {
          $media_type = MediaType::load($media_type_id);
          if (!$media_type instanceof MediaType) {
            continue;
          }
          $source_field_definition = $media_type->getSource()->getSourceFieldDefinition($media_type);
          if (is_a($source_field_definition->getItemDefinition()->getClass(), FileItem::class, TRUE)) {
            if ($target = static::prepareTarget($field_definition)) {
              $target->setPluginId($definition['id']);
              $targets[$id] = $target;
              break;
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget(FeedInterface $feed, EntityInterface $entity, $field_name, array $raw_values) {
    // Store feed and entity in context for use in prepareValue().
    $this->feedContext = $feed;
    $this->entityContext = $entity;

    try {
      parent::setTarget($feed, $entity, $field_name, $raw_values);
    }
    finally {
      // Clear context.
      $this->feedContext = NULL;
      $this->entityContext = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    if (!isset($values['target_id']) || strlen(trim($values['target_id'])) === 0) {
      throw new EmptyFeedException();
    }

    // Remove query parameter from URL to prevent invalid extension error.
    $raw_value = strtok($values['target_id'], '?');
    $reference_by = $this->configuration['reference_by'];

    // Handle direct media entity reference (ID, UUID, or other fields).
    // This uses standard EntityReference resolution.
    if (!$this->isFileSourceField($reference_by)) {
      $target_ids = $this->findEntities($reference_by, $raw_value);
      if (!empty($target_ids)) {
        $values['target_id'] = reset($target_ids);
        return;
      }
      throw new ReferenceNotFoundException($this->t('Referenced media entity not found for field %field with value %target_id.', [
        '%target_id' => $raw_value,
        '%field' => $reference_by,
      ]));
    }

    // Handle file-based input (file ID reference or URL/local path).
    // This requires file resolution, then media creation.
    $file = $this->resolveFileForMedia($raw_value, $reference_by);
    if (!$file instanceof FileInterface) {
      throw new TargetValidationException($this->t('Could not resolve file from input: %input', [
        '%input' => $raw_value,
      ]));
    }

    // Determine media type.
    $media_type_id = $this->getMediaType($file);

    // Find or create media entity.
    $media = $this->findOrCreateMedia($file, $media_type_id);

    $values['target_id'] = $media->id();
  }

  /**
   * Resolves a file entity from input that requires file import.
   *
   * @param mixed $raw_value
   *   The raw input value (can be URL, path, file ID, or other value).
   * @param string $reference_by
   *   The configured "reference_by" field.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if not found/created.
   */
  protected function resolveFileForMedia($raw_value, string $reference_by): ?FileInterface {
    $existing_value = $this->configuration['existing'] ?? 'ignore';
    $existing = $this->normalizeFileExists($existing_value);
    $search_field = ($reference_by === 'file_id') ? 'fid' : $reference_by;
    $options = [
      'existing' => $existing,
      'fields' => [$search_field],
      'file_extensions' => $this->mediaSettings['file_extensions'] ?? [],
    ];

    // Only set directory option if input is a string (URL or path).
    // For non-string values (for example file IDs), FileResolver will use
    // resolveByFields() which doesn't require a directory.
    if (is_string($raw_value)) {
      $options['directory'] = $this->getDestinationDirectory($raw_value);
    }

    // Add owner_id if available.
    $owner_id = $this->getOwnerId();
    if ($owner_id !== NULL) {
      $options['owner_id'] = $owner_id;
    }

    try {
      return $this->fileResolver->resolve($raw_value, $options);
    }
    catch (InvalidFileExtensionException $e) {
      // Re-throw InvalidFileExtensionException as TargetValidationException.
      throw new TargetValidationException($this->t('The file, %url, failed to save because the extension, %ext, is invalid.', [
        '%url' => $raw_value,
        '%ext' => $e->getExtension(),
      ]), 0, $e);
    }
    catch (DownloadException $e) {
      // Re-throw DownloadException as TargetValidationException.
      if ($e->getStatusCode() !== NULL) {
        throw new TargetValidationException($this->t('Download of %url failed with code @code.', [
          '%url' => $e->getUrl(),
          '@code' => $e->getStatusCode(),
        ]), 0, $e);
      }
      elseif ($e->getErrorMessage() !== NULL) {
        throw new TargetValidationException($this->t('Download of %url failed: @error', [
          '%url' => $e->getUrl(),
          '@error' => $e->getErrorMessage(),
        ]), 0, $e);
      }
      else {
        throw new TargetValidationException($e->getMessage(), 0, $e);
      }
    }
    catch (\Exception $e) {
      // Re-throw other exceptions as TargetValidationException.
      throw new TargetValidationException($e->getMessage(), 0, $e);
    }
  }

  /**
   * Checks if reference_by field is a file source field on media.
   *
   * @param string $field_name
   *   The field name to check.
   *
   * @return bool
   *   TRUE if the field is a file source field, FALSE otherwise.
   */
  protected function isFileSourceField(string $field_name): bool {
    // 'file_id' is the special key for file ID reference.
    if ($field_name === 'file_id') {
      return TRUE;
    }

    // Check if this is one of the source fields from media bundles.
    foreach ($this->mediaSettings['bundles'] ?? [] as $bundle_settings) {
      if (isset($bundle_settings['source_field']) && $bundle_settings['source_field'] === $field_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Finds or creates a media entity for a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $media_type_id
   *   The media bundle type.
   *
   * @return \Drupal\media\MediaInterface
   *   The media entity.
   */
  protected function findOrCreateMedia(FileInterface $file, string $media_type_id): MediaInterface {
    // Check if media already exists for this file.
    $source_field = $this->mediaSettings['bundles'][$media_type_id]['source_field'];
    $query = $this->entityTypeManager->getStorage('media')->getQuery();
    $query->condition($source_field, $file->id());
    $query->condition('bundle', $media_type_id);
    $query->accessCheck(FALSE);
    $mids = $query->execute();

    if (!empty($mids)) {
      return MediaEntity::load(reset($mids));
    }

    // Create new media entity.
    return $this->createMedia($file, $media_type_id);
  }

  /**
   * Creates a media entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string $media_type_id
   *   The media's bundle.
   *
   * @return \Drupal\media\MediaInterface
   *   A media entity.
   */
  protected function createMedia(FileInterface $file, string $media_type_id): MediaInterface {
    $source_field = $this->mediaSettings['bundles'][$media_type_id]['source_field'];
    $entity_target = $this->entityContext ? $this->getEntityTarget($this->feedContext, $this->entityContext) : NULL;

    if ($this->configuration['media_name'] === self::FILE_NAME) {
      $label = $file->label();
    }
    else {
      $label = $entity_target ? $entity_target->label() : $file->label();
    }

    // Create values for the new entity.
    $values = [
      'name' => $label,
      'bundle' => $media_type_id,
      'uid' => $this->getOwnerId() ?? 0,
      $source_field => [
        'target_id' => $file->id(),
        'alt' => $label,
      ],
    ];

    // Set language if the entity type supports it.
    if ($langcode = $this->getLangcodeKey()) {
      $values[$langcode] = $this->getLangcode();
    }

    $media = MediaEntity::create($values);
    $media->setPublished(TRUE);
    $media->save();

    return $media;
  }

  /**
   * Determines the processor owner ID for created entities.
   *
   * @return int|null
   *   The owner ID, or NULL if no owner can be determined.
   */
  protected function getOwnerId(): ?int {
    if (!$this->feedContext instanceof FeedInterface) {
      return NULL;
    }

    $feed_processor = $this->feedContext->getType()->getProcessor();
    if (!$feed_processor instanceof EntityProcessorInterface) {
      return NULL;
    }

    // Check if feed author should be used as owner.
    $use_feed_author = $feed_processor->getConfiguration('owner_feed_author') ?? FALSE;
    if ($use_feed_author) {
      $owner_id = $this->feedContext->getOwnerId();
      if ($owner_id) {
        return $owner_id;
      }
    }

    // Otherwise, use processor's configured owner ID.
    $owner_id = $feed_processor->getConfiguration('owner_id') ?? 0;
    return $owner_id > 0 ? $owner_id : NULL;
  }

  /**
   * Prepares destination directory and returns its path.
   *
   * If the file extension cannot be determined from input data, the first media
   * type is used.
   *
   * @param string $input
   *   The input data for the file (url or path).
   *
   * @return string
   *   The directory to save the file to.
   *
   * @todo The behavior for always returning a media type may change in the
   * future.
   */
  protected function getDestinationDirectory(string $input): string {
    // Use default_to_first = TRUE for destination directory determination, so
    // we always get a valid media type even if extension doesn't match.
    $media_type_id = $this->getMediaType($input, TRUE);
    $destination = $this->token->replace($this->mediaSettings['bundles'][$media_type_id]['uri_scheme'] . '://' . trim($this->mediaSettings['bundles'][$media_type_id]['file_directory'], '/'));
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    return $destination;
  }

  /**
   * Returns the media type that matches based on the file extension.
   *
   * @param \Drupal\file\FileInterface|string $input
   *   The file entity, URL, or file path.
   * @param bool $default_to_first
   *   (optional) If TRUE, returns first bundle if no match found. If FALSE,
   *   throws exception. Defaults to FALSE.
   *
   * @return string
   *   The media type id.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the media type could not be determined and $default_to_first is
   *   FALSE.
   */
  protected function getMediaType(FileInterface|string $input, bool $default_to_first = FALSE): string {
    $extension = $this->fileResolver->getFileExtension($input);

    // Determine source string for error messages.
    if ($input instanceof FileInterface) {
      $source = $input->getFileUri();
    }
    else {
      $source = $input;
    }

    return $this->getMediaTypeByExtension($extension, $source, $default_to_first);
  }

  /**
   * Determines media type based on file extension.
   *
   * @param string $extension
   *   The file extension (without leading dot).
   * @param string $source
   *   The source URL or path (for error messages).
   * @param bool $default_to_first
   *   If TRUE, returns first bundle if no match found. If FALSE, throws
   *   exception.
   *
   * @return string
   *   The media type ID.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the media type could not be determined and $default_to_first is
   *   FALSE.
   */
  protected function getMediaTypeByExtension(string $extension, string $source, bool $default_to_first = FALSE): string {
    $bundles = $this->mediaSettings['bundles'];

    // If there is only one bundle then return it.
    if (count($bundles) === 1) {
      return array_key_first($bundles);
    }

    // When multiple bundles then find the best based on the extension.
    foreach ($bundles as $media_type_id => $source_field_settings) {
      $extensions = $source_field_settings['file_extensions'] ?? [];
      if (in_array($extension, $extensions, TRUE)) {
        return $media_type_id;
      }
    }

    // Default to first bundle if cannot determine and default_to_first is TRUE.
    if ($default_to_first) {
      return array_key_first($bundles);
    }

    throw new TargetValidationException(sprintf('Media type could not be determined for %s.', $source));
  }

  /**
   * Gets media fields settings from configuration.
   *
   * Extracts settings from media bundles that have file-based source fields.
   * Only processes media types whose source field extends FileItem.
   *
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return array
   *   The media settings array with the following keys:
   *   - 'file_extensions' (array): Array of all unique file extensions from all
   *     processed media bundles.
   *   - 'bundles' (array): Array keyed by media type ID, each containing:
   *     - All source field settings from the media type's source field
   *       definition (for example 'uri_scheme', 'file_directory',
   *       'file_extensions').
   *     - 'source_field' (string): The machine name of the source field on the
   *       media type (for example 'field_media_image').
   *     - 'file_extensions' (array): Array of file extensions for this bundle
   *       (normalized from comma/space-separated string to array).
   */
  protected function getMediaFieldsSettings(array $configuration): array {
    // Initialize settings with empty file extensions array.
    $settings = [
      'file_extensions' => [],
    ];

    // Get target definition from configuration.
    // This contains the field definition for the entity reference field
    // that references media entities.
    $target_definition = $configuration['target_definition'] ?? NULL;
    if (!$target_definition) {
      return $settings;
    }

    // Get the media field settings from the target definition.
    // This tells us which media bundles are allowed for this field.
    $media_field_settings = $target_definition->getFieldDefinition()->getSettings();
    if (empty($media_field_settings['handler_settings']['target_bundles'])) {
      return $settings;
    }

    // Process each allowed media bundle.
    foreach ($media_field_settings['handler_settings']['target_bundles'] as $media_type_id) {
      // Load the media type entity.
      $media_type = MediaType::load($media_type_id);
      // Get the source field definition for this media type.
      // This is the field that stores the actual file (for example
      // field_media_image).
      $source_field_definition = $media_type->getSource()->getSourceFieldDefinition($media_type);

      // Only process media types whose source field extends FileItem.
      // This ensures we only handle file-based media types, not remote video
      // or other non-file sources.
      $class = $source_field_definition->getItemDefinition()->getClass();
      if ($class === FileItem::class || is_subclass_of($class, FileItem::class)) {
        // Copy all source field settings (uri_scheme, file_directory, etc.)
        // to the bundle settings.
        $settings['bundles'][$media_type_id] = $source_field_definition->getSettings();

        // Add the source field name for easy reference.
        $settings['bundles'][$media_type_id]['source_field'] = $source_field_definition->getName();

        // Normalize file extensions: convert from string to array.
        // Source field settings may have extensions as comma or space-separated
        // string, or already as an array. Convert to array format.
        $extensions = $settings['bundles'][$media_type_id]['file_extensions'] ?? [];
        if (is_string($extensions)) {
          // Replace commas with spaces, then split by spaces.
          $normalized_extensions_string = str_replace(',', ' ', $extensions);
          $bundle_extensions = array_filter(explode(' ', $normalized_extensions_string));
        }
        else {
          // Already an array, just filter empty values.
          $bundle_extensions = array_filter((array) $extensions);
        }
        $settings['bundles'][$media_type_id]['file_extensions'] = $bundle_extensions;

        // Merge this bundle's extensions into the global list.
        $settings['file_extensions'] = array_merge($settings['file_extensions'], $bundle_extensions);
      }
    }

    // Remove duplicates from the global file extensions array.
    $settings['file_extensions'] = array_values(array_unique($settings['file_extensions']));
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityType() {
    return 'media';
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundles() {
    return array_keys($this->mediaSettings['bundles'] ?? []);
  }

  /**
   * Whether this mapper can use file-based import for at least one bundle.
   *
   * Allowed media bundles for the mapped field are collected in
   * getMediaFieldsSettings(). Only types whose Media source field is a file
   * field (FileItem or subclass) are included; each bundle entry stores the
   * source field machine name under 'source_field'.
   *
   * When TRUE, the mapped source may provide a file ID, URL, or path and
   * Feeds will resolve or create a file and then find or create media that
   * uses that file on the source field. When FALSE, only direct media entity
   * lookup (for example by ID on another field) applies, so we omit the
   * "File ID" reference option and related defaults or help text.
   *
   * @return bool
   *   TRUE if at least one bundle has a recorded file source field.
   */
  protected function hasFileBackedMediaBundle(): bool {
    foreach ($this->mediaSettings['bundles'] ?? [] as $bundle_settings) {
      if (isset($bundle_settings['source_field'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Adds media-specific reference options:
   * - 'file_id': Reference by file ID (works with any media type).
   */
  protected function getPotentialFields() {
    $fields = parent::getPotentialFields();

    // Add a single "File ID" option if file source fields exist.
    if ($this->hasFileBackedMediaBundle() && !isset($fields['file_id'])) {
      $fields['file_id'] = $this->t('File ID');
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Display warning about known limitations.
    $warning_message = Markup::create(
      '<div class="messages messages--warning" role="alert">' .
      '<h2 class="visually-hidden">' . $this->t('Warning message') . '</h2>' .
      '<div>' . $this->t('⚠️ Some Media configurations are not fully supported yet:') . '</div>' .
      '<ul>' .
      '<li>' . $this->t('Media reference fields that allow multiple media types may not import correctly.') . '</li>' .
      '<li>' . $this->t('Importing remote videos is currently not supported.') . '</li>' .
      '<li>' . $this->t('Importing media into multiple languages has not been tested yet and may not work.') . '</li>' .
      '</ul>' .
      '</div>'
    );
    $form['warning'] = [
      '#type' => 'markup',
      '#markup' => $warning_message,
      '#weight' => -10,
    ];

    // Add clarification about reference_by behavior.
    $reference_by_field = &$form['reference_by'];
    if (isset($reference_by_field) && $this->hasFileBackedMediaBundle()) {
      $reference_by_field['#description'] = $this->t('When "Reference by" is set to "File ID", the value is interpreted as a file reference. Otherwise, it is treated as a direct media entity lookup field.');
    }

    $media_name_options = [
      self::ENTITY_NAME => $this->t('Entity name'),
      self::FILE_NAME => $this->t('File name'),
    ];

    $form['media_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Generate media name from'),
      '#options' => $media_name_options,
      '#default_value' => $this->configuration['media_name'],
      '#description' => $this->t('When using %entity_name, it is recommended to map the entity label first (for example node title) before mapping the media target. If %entity_name is still empty during import, Drupal Media may populate the media name from source metadata when saving the media entity.', [
        '%entity_name' => $media_name_options[self::ENTITY_NAME],
      ]),
    ];

    // Normalize existing value for form default.
    $existing_value = $this->configuration['existing'] ?? 'ignore';
    $existing_enum = $this->normalizeFileExists($existing_value);
    // Get the case name as string for storage.
    $existing_name = $this->getFileExistsName($existing_enum);

    $form['existing'] = [
      '#type' => 'select',
      '#title' => $this->t('Handle existing files'),
      '#options' => [
        'replace' => $this->t('Replace'),
        'rename' => $this->t('Rename'),
        'ignore' => $this->t('Ignore'),
      ],
      '#default_value' => $existing_name,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [
      'media_name' => self::ENTITY_NAME,
      'existing' => 'ignore',
    ] + parent::defaultConfiguration();

    // Default reference_by should be 'file_id' (File ID). This makes file URLs
    // the default expectation.
    if ($this->hasFileBackedMediaBundle()) {
      $config['reference_by'] = 'file_id';
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

    $media_name = $this->configuration['media_name'] === self::FILE_NAME ? $this->t('File name') : $this->t('Entity name');
    $summary[] = $this->t('Generate media name from: %media_name', ['%media_name' => $media_name]);

    $existing = $this->normalizeFileExists($this->configuration['existing'] ?? 'ignore');
    $message = match ($existing) {
      FileExists::Replace => $this->t('Replace'),
      FileExists::Rename => $this->t('Rename'),
      FileExists::Error => $this->t('Ignore'),
    };
    $summary[] = $this->t('Existing files: %existing', ['%existing' => $message]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    // Only reference media entities.
    $type = $field_definition->getSetting('target_type');

    if (!\Drupal::entityTypeManager()->getDefinition($type)->entityClassImplements(MediaInterface::class)) {
      return;
    }

    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('target_id');
  }

  /**
   * {@inheritdoc}
   */
  protected function hasAutocreateSupport() {
    return FALSE;
  }

}
