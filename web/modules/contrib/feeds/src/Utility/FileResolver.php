<?php

namespace Drupal\feeds\Utility;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\DownloadException;
use Drupal\feeds\Exception\FileNotFoundException;
use Drupal\feeds\Exception\InvalidFileExtensionException;
use Drupal\feeds\Feeds\Target\FileExistsTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service to resolve input values to file entities.
 *
 * This service can be extended by custom modules to add custom behavior such
 * as authentication for URL downloads. The recommended approach is to extend
 * this class and override the protected downloadFile() method.
 *
 * @see docs/EXTENDING_FILERESOLVER.md
 *   For detailed examples on how to extend this service.
 * @see \Drupal\feeds\Utility\FileResolverInterface
 */
class FileResolver implements FileResolverInterface {

  use FileExistsTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity finder service.
   *
   * @var \Drupal\feeds\EntityFinderInterface
   */
  protected $entityFinder;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface|null
   */
  protected $fileRepository;

  /**
   * Constructs a FileResolver object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\feeds\EntityFinderInterface $entity_finder
   *   The entity finder service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   * @param \Drupal\file\FileRepositoryInterface|null $file_repository
   *   The file repository service (optional, may not be available if file
   *   module is not installed).
   */
  public function __construct(ClientInterface $client, EntityTypeManagerInterface $entity_type_manager, EntityFinderInterface $entity_finder, FileSystemInterface $file_system, StreamWrapperManagerInterface $stream_wrapper_manager, AccountInterface $current_user, ?FileRepositoryInterface $file_repository = NULL) {
    $this->client = $client;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFinder = $entity_finder;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->currentUser = $current_user;
    $this->fileRepository = $file_repository;
  }

  /**
   * Creates a FileResolver instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new FileResolver instance.
   */
  public static function create(ContainerInterface $container): static {
    $file_repository = NULL;
    if ($container->has('file.repository')) {
      $file_repository = $container->get('file.repository');
    }

    return new static(
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('feeds.entity_finder'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('current_user'),
      $file_repository
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(mixed $input, array $options = []): ?FileInterface {
    if (empty($input)) {
      return NULL;
    }

    // Search by file ID by default.
    if (!isset($options['fields'])) {
      $options['fields'] = ['fid'];
    }

    // First try to find an existing entity.
    $existing_file = $this->resolveByFields($input, $options);
    if ($existing_file instanceof FileInterface) {
      return $existing_file;
    }

    // Try to resolve by url or local path.
    if ($this->isUrl($input)) {
      return $this->resolveUrl($input, $options);
    }
    if ($this->isPath($input)) {
      return $this->resolvePath($input, $options);
    }
    return NULL;
  }

  /**
   * Checks if the input is a URL.
   *
   * @param mixed $input
   *   The input value.
   *
   * @return bool
   *   TRUE if the input is a URL, FALSE otherwise.
   */
  protected function isUrl(mixed $input): bool {
    return is_string($input) && (strpos($input, 'http://') === 0 || strpos($input, 'https://') === 0);
  }

  /**
   * Checks if the input is a file path.
   *
   * @param mixed $input
   *   The input value.
   *
   * @return bool
   *   TRUE if the input is a file path, FALSE otherwise.
   */
  protected function isPath(mixed $input): bool {
    if (!is_string($input) || empty($input)) {
      return FALSE;
    }

    // Check if it's a stream wrapper (e.g., public://, private://).
    $scheme_pos = strpos($input, '://');
    if ($scheme_pos !== FALSE) {
      $scheme = substr($input, 0, $scheme_pos);
      return in_array($scheme, stream_get_wrappers(), TRUE);
    }

    // Check if it's an absolute server path.
    return $input[0] === '/' || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:\\\\/i', $input));
  }

  /**
   * Builds a path to a file.
   *
   * @param string $directory
   *   The directory where the file is in.
   * @param string $filename
   *   The name of the file.
   *
   * @return string
   *   The built file path.
   */
  protected function composeFilePath(string $directory, string $filename): string {
    // Ensure that any trailing slashes from the directory input are removed.
    $directory = rtrim($directory, '/');
    // For directories like 'public://' the trailing slash should not be
    // removed, so in that case we add it back.
    if (preg_match('#^([a-z]+):$#', $directory, $matches)) {
      $directory = $matches[1] . '://';
    }
    // Add separator only if directory doesn't end with ://.
    $separator = (substr($directory, -3) === '://') ? '' : '/';
    return $directory . $separator . $filename;
  }

  /**
   * Resolves a URL to a file entity.
   *
   * @param string $url
   *   The URL to download.
   * @param array $options
   *   Options for resolving:
   *   - directory: (string, required) The directory where to save the file.
   *   - existing: (int, required) How to handle existing files.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if no file could be resolved.
   *
   * @throws \RuntimeException
   *   If file.repository service is not available.
   * @throws \InvalidArgumentException
   *   If required options are missing.
   */
  protected function resolveUrl(string $url, array $options): ?FileInterface {
    // Check if required options are set.
    $this->validateUrlPathOptions($options);

    if (!$this->fileRepository) {
      throw new \RuntimeException('The file.repository service is not available. The file module must be installed.');
    }

    // Determine filename from URL.
    $filename = $this->getFileNameFromUrl($url);
    $destination = $this->composeFilePath($options['directory'], $filename);

    // Validate file extension before downloading (if the "file_extensions"
    // option is provided).
    $this->validateFileExtension($destination, $options);

    // Check if the file already exists at the destination.
    $destination_real_path = $this->fileSystem->realpath($destination);
    // Only check file_exists if we have a real path. If realpath returns NULL,
    // the file doesn't exist (stream wrapper may not be available in unit
    // tests).
    $destination_exists = $destination_real_path ? file_exists($destination_real_path) : FALSE;

    // If the file exists at the destination and we should skip file writes
    // in this case (option 'existing' is set to 'ignore'), check for an
    // existing file entity by URI.
    $existing = $this->normalizeFileExists($options['existing']);
    if ($destination_exists && $existing === FileExists::Error) {
      $file = $this->fileRepository->loadByUri($destination);
      if ($file instanceof FileInterface) {
        // An existing file is found, return that instead of downloading
        // the one from the url.
        return $file;
      }
    }

    // Download the file content.
    $content = $this->downloadFile($url);

    if ($destination_exists) {
      // If there is already an existing file at the destination, check
      // if that one is exactly the same as the one we just downloaded.
      $destination_hash_path = $destination_real_path ?: $destination;
      $existing_hash = hash_file('sha256', $destination_hash_path);
      $new_hash = hash('sha256', $content);

      if ($existing_hash === $new_hash) {
        // Same content, find or create file entity for existing file.
        return $this->findOrCreateFileEntity($destination);
      }

      // Different content, handle according to 'existing' setting.
      return $this->handleDifferentContent($content, $destination, $existing, $options);
    }

    // File doesn't exist yet, save it and create file entity.
    return $this->saveFileAndCreateEntity($content, $destination, FileExists::Replace, $options);
  }

  /**
   * Resolves a file path to a file entity.
   *
   * @param string $path
   *   The file path (can be server path or stream).
   * @param array $options
   *   Options for resolving:
   *   - directory: (string, required) The directory where to save the file.
   *   - existing: (\Drupal\Core\File\FileExists|int|string, required) How to
   *     handle existing files. Can be a FileExists enum, legacy integer
   *     constant, or string name.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if no file could be resolved.
   *
   * @throws \RuntimeException
   *   If file.repository service is not available.
   * @throws \InvalidArgumentException
   *   If required options are missing.
   * @throws \Drupal\feeds\Exception\FileNotFoundException
   *   In case the requested file cannot be found.
   */
  protected function resolvePath(string $path, array $options): ?FileInterface {
    // Check if required options are set.
    $this->validateUrlPathOptions($options);

    if (!$this->fileRepository) {
      throw new \RuntimeException('The file.repository service is not available. The file module must be installed.');
    }

    // Convert path to real path if it's a stream wrapper.
    $path_scheme = strpos($path, '://') !== FALSE ? strstr($path, '://', TRUE) : FALSE;
    $is_stream = $path_scheme !== FALSE && $this->streamWrapperManager->isValidScheme($path_scheme);
    $real_path = $is_stream ? $this->fileSystem->realpath($path) : $path;

    if (!is_string($real_path) || !file_exists($real_path)) {
      throw new FileNotFoundException(sprintf('The file %s does not exist.', $real_path));
    }

    // Determine filename from original path (preserve stream wrapper if
    // applicable).
    $filename = basename($path);
    $destination = $this->composeFilePath($options['directory'], $filename);

    // Validate file extension before processing (if the "file_extensions"
    // option is provided).
    $this->validateFileExtension($destination, $options);

    // Check if source and destination are the same. If so, then the file can be
    // used as is. No copy or comparing file contents will be necessary.
    $destination_real_path = $this->fileSystem->realpath($destination);
    if ($real_path === $destination_real_path) {
      // Same file, find or create file entity.
      return $this->findOrCreateFileEntity($destination);
    }

    $destination_to_check = is_string($destination_real_path) ? $destination_real_path : $destination;

    // Check if file exists at destination.
    if (file_exists($destination_to_check)) {
      // Normalize 'existing' option to check if it's 'ignore'.
      $existing_enum = $this->normalizeFileExists($options['existing']);

      // If 'existing' is 'ignore', we can skip file comparison and reading
      // since no file will be written anyway. Just return the existing file.
      if ($existing_enum === FileExists::Error) {
        return $this->findOrCreateFileEntity($destination);
      }

      // Check if the file contents are exactly the same. If this is the case,
      // there is no need to create a new file (in case of renaming files) or
      // overwrite the existing file (in case of replacing files).
      $existing_hash = hash_file('sha256', $destination_to_check);
      $source_hash = hash_file('sha256', $real_path);
      if ($existing_hash === $source_hash) {
        // Same content, find or create file entity for existing file.
        return $this->findOrCreateFileEntity($destination);
      }

      // Different content, handle according to 'existing' setting.
      // Read source file content.
      $content = file_get_contents($real_path);
      return $this->handleDifferentContent($content, $destination, $existing_enum, $options);
    }

    // File doesn't exist at destination, copy it.
    $content = file_get_contents($real_path);
    return $this->saveFileAndCreateEntity($content, $destination, $options['existing'], $options);
  }

  /**
   * Resolves a value to a file entity by searching in specified fields.
   *
   * @param mixed $value
   *   The value to search for.
   * @param array $options
   *   Options for resolving:
   *   - fields: (array, required) The fields on the file entity to search.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if no file could be resolved.
   *
   * @throws \InvalidArgumentException
   *   If required options are missing.
   */
  protected function resolveByFields(mixed $value, array $options): ?FileInterface {
    if (!isset($options['fields']) || !is_array($options['fields']) || $options['fields'] === []) {
      throw new \InvalidArgumentException('The "fields" option is required when resolving by fields.');
    }

    $fields = $options['fields'];

    // If 'fid' or 'id' is in fields, try direct entity load first.
    if (is_numeric($value) && (in_array('fid', $fields) || in_array('id', $fields))) {
      $fid = (int) $value;
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file instanceof FileInterface) {
        return $file;
      }
    }

    // Search using entity finder for each field.
    foreach ($fields as $field) {
      if ($field === 'fid' || $field === 'id') {
        // Skip consulting entity finder for the 'fid' or 'id' fields, because
        // these should only be used for a direct entity load.
        continue;
      }
      $entity_ids = $this->entityFinder->findEntities('file', $field, $value);
      if (!empty($entity_ids)) {
        $fid = reset($entity_ids);
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        if ($file instanceof FileInterface) {
          return $file;
        }
      }
    }

    return NULL;
  }

  /**
   * Validates options for URL and Path inputs.
   *
   * @param array $options
   *   The options to validate.
   *
   * @throws \InvalidArgumentException
   *   If required options are missing.
   */
  protected function validateUrlPathOptions(array $options): void {
    if (!isset($options['directory']) || empty($options['directory'])) {
      throw new \InvalidArgumentException('The "directory" option is required for URL and Path inputs.');
    }

    if (!isset($options['existing'])) {
      throw new \InvalidArgumentException('The "existing" option is required for URL and Path inputs.');
    }
  }

  /**
   * Downloads a file from a URL.
   *
   * This method can be overridden by extending classes or service decorators
   * to add custom behavior such as authentication headers, custom request
   * options, or alternative download mechanisms.
   *
   * @param string $url
   *   The URL to download from.
   *
   * @return string
   *   The file content.
   *
   * @throws \Drupal\feeds\Exception\DownloadException
   *   If the download failed (HTTP error status code or request exception).
   */
  protected function downloadFile(string $url): string {
    try {
      $response = $this->client->request('GET', $url);
      if ($response->getStatusCode() >= 400) {
        throw new DownloadException($url, $response->getStatusCode());
      }
      return (string) $response->getBody();
    }
    catch (ClientException $e) {
      // ClientException is thrown for HTTP 4xx errors.
      // Extract the status code from the response if available.
      $response = $e->getResponse();
      if ($response !== NULL) {
        throw new DownloadException($url, $response->getStatusCode(), NULL, $e);
      }
      // Fallback: treat as generic request exception.
      throw new DownloadException($url, NULL, $e->getMessage(), $e);
    }
    catch (RequestException $e) {
      // Other RequestExceptions (e.g., network errors, timeouts).
      throw new DownloadException($url, NULL, $e->getMessage(), $e);
    }
  }

  /**
   * Extracts the filename from a URL.
   *
   * @param string $url
   *   The URL.
   *
   * @return string
   *   The filename.
   */
  protected function getFileNameFromUrl(string $url): string {
    $filename = trim(basename($url), " \t\n\r\0\x0B.");
    // Remove query string from filename, if it has one.
    [$filename] = explode('?', $filename);
    return $filename;
  }

  /**
   * Handles the case when file content differs.
   *
   * @param string $content
   *   The new file content.
   * @param string $destination
   *   The destination path.
   * @param \Drupal\Core\File\FileExists|int|string $existing
   *   How to handle existing files. Can be a FileExists enum, legacy integer
   *   constant, or string name.
   * @param array $options
   *   (optional) Additional options, including file_extensions.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL.
   */
  protected function handleDifferentContent(string $content, string $destination, FileExists|int|string $existing, array $options = []): ?FileInterface {
    // Validate file extension before saving.
    $this->validateFileExtension($destination, $options);

    $existing_enum = $this->normalizeFileExists($existing);

    // We write the content if 'existing' is either 'replace' or 'rename'. If it
    // is 'ignore', we try to return an already existing file that we do not
    // overwrite.
    return match ($existing_enum) {
      FileExists::Replace => $this->saveFileAndCreateEntity($content, $destination, FileExists::Replace, $options),
      FileExists::Rename => $this->saveFileAndCreateEntity($content, $destination, FileExists::Rename, $options),
      FileExists::Error => $this->findOrCreateFileEntity($destination),
    };
  }

  /**
   * Saves file content and creates a file entity.
   *
   * @param string $content
   *   The file content.
   * @param string $destination
   *   The destination path.
   * @param \Drupal\Core\File\FileExists|int|string $fileExists
   *   How to handle existing files. Can be a FileExists enum, legacy integer
   *   constant, or string name.
   * @param array $options
   *   (optional) Additional options, including file_extensions.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if save failed.
   *
   * @throws \InvalidArgumentException
   *   If file extension is not allowed.
   */
  protected function saveFileAndCreateEntity(string $content, string $destination, FileExists|int|string $fileExists, array $options = []): ?FileInterface {
    // Validate file extension before saving.
    $this->validateFileExtension($destination, $options);

    try {
      // Ensure directory exists.
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

      // Normalize to FileExists enum if needed.
      $fileExists = $this->normalizeFileExists($fileExists);

      // Save file using file repository.
      $file = $this->fileRepository->writeData($content, $destination, $fileExists);
      if ($file instanceof FileInterface) {
        // Set the file owner to the current user if it doesn't have an owner
        // yet.
        if (!$file->getOwnerId()) {
          $file->setOwnerId($this->currentUser->id());
          $file->save();
        }
        return $file;
      }
      return NULL;
    }
    catch (EntityStorageException | FileException $e) {
      return NULL;
    }
  }

  /**
   * Finds or creates a file entity for an existing physical file.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if creation failed.
   */
  protected function findOrCreateFileEntity(string $uri): ?FileInterface {
    // Convert to URI if it's an absolute path.
    if (!empty($uri) && $uri[0] === '/') {
      // Try to convert to stream wrapper URI.
      $schemes = $this->streamWrapperManager->getWrappers();
      foreach ($schemes as $scheme => $wrapper) {
        $wrapper_path = $this->fileSystem->realpath($scheme . '://');
        if ($wrapper_path && strpos($uri, $wrapper_path) === 0) {
          $uri = $scheme . '://' . substr($uri, strlen($wrapper_path) + 1);
          break;
        }
      }
    }

    // Search for existing file entity by URI.
    $file = $this->fileRepository->loadByUri($uri);
    if ($file === NULL) {
      $file = File::create(['uri' => $uri]);
      $file->setOwnerId($this->currentUser->id());
    }

    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Validates that the file extension is allowed.
   *
   * @param string $file_path
   *   The file path to validate.
   * @param array $options
   *   Options array that may contain 'file_extensions'.
   *
   * @throws \Drupal\feeds\Exception\InvalidFileExtensionException
   *   If file_extensions is provided and the file extension is not allowed.
   */
  protected function validateFileExtension(string $file_path, array $options): void {
    if (empty($options['file_extensions']) || !is_array($options['file_extensions'])) {
      // No file extensions restriction, skip validation.
      return;
    }

    // Extract file extension from path.
    $filename = basename($file_path);
    // Remove query string if present.
    [$filename] = explode('?', $filename);
    $extension = '';
    if (($pos = strrpos($filename, '.')) !== FALSE) {
      $extension = strtolower(substr($filename, $pos + 1));
    }

    // Normalize file extensions to lowercase for comparison.
    $allowed_extensions = array_map('strtolower', array_filter($options['file_extensions']));

    // Check if extension is allowed.
    if (!empty($extension) && !in_array($extension, $allowed_extensions, TRUE)) {
      throw new InvalidFileExtensionException($file_path, $extension, $allowed_extensions);
    }
  }

}
