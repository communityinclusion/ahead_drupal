<?php

namespace Drupal\feeds\Feeds\Target;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\DownloadException;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\FileNotFoundException;
use Drupal\feeds\Exception\InvalidFileExtensionException;
use Drupal\feeds\Exception\TargetValidationException;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Utility\FileResolverInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a file field mapper.
 *
 * @FeedsTarget(
 *   id = "file",
 *   field_types = {"file"}
 * )
 */
class File extends EntityReference {

  use FileExistsTrait;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The list of allowed file extensions.
   *
   * @var string[]
   */
  protected $fileExtensions;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The file and stream wrapper helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository.
   *
   * @var Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The system.file configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $fileConfig;

  /**
   * The file resolver service.
   *
   * @var \Drupal\feeds\Utility\FileResolverInterface
   */
  protected $fileResolver;

  /**
   * Constructs a File object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\feeds\EntityFinderInterface $entity_finder
   *   The Feeds entity finder service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file and stream wrapper helper.
   * @param \Drupal\file\FileRepositoryInterface|null $file_repository
   *   The file repository.
   * @param \Drupal\Core\Config\ImmutableConfig $file_config
   *   The system.file configuration.
   * @param \Drupal\feeds\Utility\FileResolverInterface $file_resolver
   *   The file resolver service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ClientInterface $client, Token $token, EntityFieldManagerInterface $entity_field_manager, EntityFinderInterface $entity_finder, FileSystemInterface $file_system, FileRepositoryInterface $file_repository, ImmutableConfig $file_config, FileResolverInterface $file_resolver) {
    $this->client = $client;
    $this->token = $token;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $entity_finder);

    $extensions = preg_replace('/([, ]+\.?)/', ' ', trim(strtolower($this->settings['file_extensions'])));
    $this->fileExtensions = array_unique(array_filter(explode(' ', $extensions)));

    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->fileConfig = $file_config;
    $this->fileResolver = $file_resolver;
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
      $container->get('http_client'),
      $container->get('token'),
      $container->get('entity_field.manager'),
      $container->get('feeds.entity_finder'),
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('config.factory')->get('system.file'),
      $container->get('feeds.file_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('target_id')
      ->addProperty('description');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    foreach ($values as $column => $value) {
      switch ($column) {
        case 'description':
          $values[$column] = (string) $value;
          break;

        case 'target_id':
          $values[$column] = $this->getFile($value);
          break;
      }
    }

    $values['display'] = (int) $this->settings['display_default'];
  }

  /**
   * {@inheritdoc}
   *
   * Filesize and MIME-type aren't sensible fields to match on so these are
   * filtered out.
   */
  protected function filterFieldTypes(FieldStorageDefinitionInterface $field) {
    $ignore_fields = [
      'filesize',
      'filemime',
    ];

    return in_array($field->getName(), $ignore_fields) ? FALSE : parent::filterFieldTypes($field);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityType() {
    return 'file';
  }

  /**
   * {@inheritdoc}
   *
   * The file entity doesn't support any bundles. Providing an empty array here
   * will prevent the bundle check from being added in the find entity query.
   */
  protected function getBundles() {
    return [];
  }

  /**
   * Returns a file id given a url, path, or other value.
   *
   * @param mixed $value
   *   A URL, file path, or other scalar value.
   *
   * @return int
   *   The file id.
   *
   * @throws \Drupal\feeds\Exception\EmptyFeedException
   *   In case an empty file value is given.
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the file could not be resolved or saved.
   */
  protected function getFile($value) {
    if (empty($value)) {
      // No file.
      throw new EmptyFeedException('The given file value is empty.');
    }

    // First try to find an existing file entity using the configured reference
    // method.
    if (FALSE !== ($fid = $this->findEntity($this->configuration['reference_by'], $value))) {
      return $fid;
    }

    // If a custom module has overridden getFileName(), getContent(), or
    // writeData(), use the legacy code path during the deprecation period. This
    // allows modules that added custom logic to continue working until they
    // migrate to extending FileResolver. Only applies when the value looks like
    // a URL (contains ://), since the legacy path expected URLs.
    // @todo Remove in feeds:4.0.0.
    if ($this->hasOverriddenLegacyMethods() && is_string($value) && strpos($value, '://') !== FALSE) {
      return $this->getFileLegacy($value);
    }

    // Prepare all options for FileResolver. The service will determine which
    // options are needed based on the input type (URL, path, or other value).
    $directory = $this->getDestinationDirectory();
    $existing = $this->normalizeFileExists($this->configuration['existing'] ?? 'ignore');
    $options = [
      'directory' => $directory,
      'existing' => $existing,
      'fields' => [$this->configuration['reference_by']],
      'file_extensions' => $this->fileExtensions,
    ];

    // Use FileResolver service to resolve the file.
    try {
      $file = $this->fileResolver->resolve($value, $options);
      if ($file instanceof FileInterface) {
        return $file->id();
      }
    }
    catch (InvalidFileExtensionException $e) {
      // Re-throw InvalidFileExtensionException as TargetValidationException.
      $url = is_string($value) ? $value : (string) $value;
      throw new TargetValidationException($this->t('The file, %url, failed to save because the extension, %ext, is not allowed.', [
        '%url' => $url,
        '%ext' => $e->getExtension(),
      ]), 0, $e);
    }
    catch (DownloadException $e) {
      // Re-throw DownloadException as TargetValidationException.
      if ($e->getStatusCode() !== NULL) {
        $args = [
          '%url' => $e->getUrl(),
          '@code' => $e->getStatusCode(),
        ];
        throw new TargetValidationException($this->t('Download of %url failed with code @code.', $args), 0, $e);
      }
      elseif ($e->getErrorMessage() !== NULL) {
        $args = [
          '%url' => $e->getUrl(),
          '@error' => $e->getErrorMessage(),
        ];
        throw new TargetValidationException($this->t('Download of %url failed: @error', $args), 0, $e);
      }
      else {
        // Fallback: use the exception message.
        throw new TargetValidationException($e->getMessage(), 0, $e);
      }
    }
    catch (FileNotFoundException $e) {
      throw new TargetValidationException($this->t('There was an error resolving the file: %file could not be found.', [
        '%file' => is_string($value) ? $value : (string) $value,
      ]));
    }
    catch (\Exception $e) {
      // Re-throw other exceptions as TargetValidationException.
      throw new TargetValidationException($e->getMessage(), 0, $e);
    }

    // If FileResolver returned NULL, throw an exception.
    throw new TargetValidationException($this->t('There was an error resolving the file: %file', [
      '%file' => is_string($value) ? $value : (string) $value,
    ]));
  }

  /**
   * Checks if any legacy method has been overridden by a subclass.
   *
   * Used for backward compatibility during the deprecation period. When a
   * custom module overrides getFileName(), getContent(), or writeData(), we
   * use the legacy code path so the override continues to work.
   *
   * @return bool
   *   TRUE if any of getFileName(), getContent(), or writeData() is defined in
   *   a class other than File.
   */
  protected function hasOverriddenLegacyMethods(): bool {
    foreach (['getFileName', 'getContent', 'writeData'] as $method) {
      $reflection = new \ReflectionMethod($this, $method);
      if ($reflection->getDeclaringClass()->getName() !== self::class) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Legacy getFile implementation for subclasses that override legacy methods.
   *
   * Replicates the pre-FileResolver logic so custom modules that override
   * getFileName(), getContent(), or writeData() continue to work. Only used
   * when hasOverriddenLegacyMethods() returns TRUE. Will be removed in
   * feeds:4.0.0.
   *
   * @param string $value
   *   The URL to download.
   *
   * @return int
   *   The file id.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the file could not be resolved or saved.
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   Extend FileResolver and override the feeds.file_resolver service instead.
   *
   * @see https://www.drupal.org/node/3565186
   */
  protected function getFileLegacy(string $value): int {
    @trigger_error('getFileLegacy() is deprecated in feeds:3.3.0 and is removed from feeds:4.0.0. Extend FileResolver and override the feeds.file_resolver service instead. See https://www.drupal.org/node/3565186', E_USER_DEPRECATED);

    $filepath = $this->getDestinationDirectory() . '/' . $this->getFileName($value);
    $existing = $this->normalizeFileExists($this->configuration['existing'] ?? 'ignore');

    if ($existing === FileExists::Error) {
      if (file_exists($filepath) && ($fid = $this->findEntity('uri', $filepath)) !== FALSE) {
        return $fid;
      }
      $file = $this->writeData($this->getContent($value), $filepath, FileExists::Replace);
    }
    else {
      $file = $this->writeData($this->getContent($value), $filepath, $existing);
    }

    if ($file instanceof FileInterface) {
      return $file->id();
    }

    throw new TargetValidationException($this->t('There was an error saving the file: %file', [
      '%file' => $filepath,
    ]));
  }

  /**
   * Prepares destination directory and returns its path.
   *
   * @return string
   *   The directory to save the file to.
   */
  protected function getDestinationDirectory() {
    $destination = $this->token->replace($this->settings['uri_scheme'] . '://' . trim($this->settings['file_directory'], '/'));
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    return $destination;
  }

  /**
   * Extracts the file name from the given url and checks for valid extension.
   *
   * @param string $url
   *   The URL to get the file name for.
   *
   * @return string
   *   The file name.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the file extension is not valid.
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead.
   *   The FileResolver service handles file name extraction and extension
   *   validation automatically.
   *
   * @see https://www.drupal.org/node/3573692
   */
  protected function getFileName($url) {
    @trigger_error(__METHOD__ . '() is deprecated in feeds:3.3.0 and is removed from feeds:4.0.0. Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead. The FileResolver service handles file name extraction and extension validation automatically. See https://www.drupal.org/node/3573692', E_USER_DEPRECATED);

    $filename = trim(basename($url), " \t\n\r\0\x0B.");
    // Remove query string from file name, if it has one.
    [$filename] = explode('?', $filename);
    $extension = substr($filename, strrpos($filename, '.') + 1);

    if (!preg_grep('/' . $extension . '/i', $this->fileExtensions)) {
      throw new TargetValidationException($this->t('The file, %url, failed to save because the extension, %ext, is invalid.', [
        '%url' => $url,
        '%ext' => $extension,
      ]));
    }

    return $filename;
  }

  /**
   * Attempts to download the file at the given url.
   *
   * @param string $url
   *   The URL to download a file from.
   *
   * @return string
   *   The file contents.
   *
   * @throws \Drupal\feeds\Exception\TargetValidationException
   *   In case the file could not be downloaded.
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead.
   *   The FileResolver service handles file downloading automatically.
   *
   * @see https://www.drupal.org/node/3573692
   */
  protected function getContent($url) {
    @trigger_error(__METHOD__ . '() is deprecated in feeds:3.3.0 and is removed from feeds:4.0.0. Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead. The FileResolver service handles file downloading automatically. See https://www.drupal.org/node/3573692', E_USER_DEPRECATED);

    $response = $this->client->request('GET', $url);

    if ($response->getStatusCode() >= 400) {
      $args = [
        '%url' => $url,
        '@code' => $response->getStatusCode(),
      ];
      throw new TargetValidationException($this->t('Download of %url failed with code @code.', $args));
    }

    return (string) $response->getBody();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['existing' => 'ignore'] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $options = [
      'replace' => $this->t('Replace'),
      'rename' => $this->t('Rename'),
      'ignore' => $this->t('Ignore'),
    ];

    // Normalize existing value for form default.
    $existing_value = $this->configuration['existing'] ?? 'ignore';
    $existing_enum = $this->normalizeFileExists($existing_value);
    // Get the case name as string for storage.
    $existing_name = $this->getFileExistsName($existing_enum);

    $form['existing'] = [
      '#type' => 'select',
      '#title' => $this->t('Handle existing files'),
      '#options' => $options,
      '#default_value' => $existing_name,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

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
   * Saves a file to the specified destination and creates a database entry.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string|null $destination
   *   (optional) A string containing the destination URI. This must be a stream
   *   wrapper URI. If no value or NULL is provided, a randomized name will be
   *   generated and the file will be saved using Drupal's default files scheme,
   *   usually "public://".
   * @param \Drupal\Core\File\FileExists|int $replace
   *   (optional) The replace behavior when the destination file already exists.
   *   Can be a FileExists enum or legacy integer constant. Possible values:
   *   - FileExists::Replace: Replace the existing file. If a managed file with
   *     the destination name exists, then its database entry will be updated.
   *     If no database entry is found, then a new one will be created.
   *   - FileExists::Rename: (default) Append _{incrementing number} until the
   *     filename is unique.
   *   - FileExists::Error: Do nothing and return FALSE.
   *
   * @return \Drupal\file\FileInterface|false
   *   A file entity, or FALSE on error.
   *
   * @deprecated in feeds:3.3.0 and is removed from feeds:4.0.0.
   *   Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead.
   *   The FileResolver service handles file saving and entity creation
   *   automatically.
   *
   * @see https://www.drupal.org/node/3573692
   */
  protected function writeData($data, $destination = NULL, /* FileExists */$replace = FileExists::Rename) {
    @trigger_error(__METHOD__ . '() is deprecated in feeds:3.3.0 and is removed from feeds:4.0.0. Use \Drupal\feeds\Utility\FileResolverInterface::resolve() instead. The FileResolver service handles file saving and entity creation automatically. See https://www.drupal.org/node/3573692', E_USER_DEPRECATED);

    if (empty($destination)) {
      $destination = $this->fileConfig->get('default_scheme') . '://';
    }
    // Normalize to FileExists enum if needed.
    if (!$replace instanceof FileExists) {
      $replace = $this->mapLegacyIntToFileExists($replace);
    }
    try {
      return $this->fileRepository->writeData($data, $destination, $replace);
    }
    catch (EntityStorageException | FileException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasAutocreateSupport() {
    return FALSE;
  }

}
