<?php

namespace Drupal\feeds\Utility;

use Drupal\file\FileInterface;

/**
 * Interface for the Feeds file resolver service.
 *
 * This service converts various input values to file entities.
 */
interface FileResolverInterface {

  /**
   * Resolves an input value to a file entity.
   *
   * @param mixed $input
   *   The input value. Can be:
   *   - A URL (string starting with "http://" or "https://");
   *   - A file path (string, can be server path or a stream like
   *     "public://foo/bar");
   *   - Any other scalar value, used to search by fields.
   * @param array $options
   *   (optional) Additional options for resolving the file:
   *   - directory: (string, required for URL and Path) The directory where to
   *     search for existing files and/or save new files.
   *   - existing: (\Drupal\Core\File\FileExists|int|string, required for URL
   *     and Path) What to do if a file with the same name but different content
   *     exists. Can be a FileExists enum, legacy integer constant, or string
   *     name. One of:
   *     - \Drupal\Core\File\FileExists::Replace: Replace
   *     - \Drupal\Core\File\FileExists::Rename: Rename
   *     - \Drupal\Core\File\FileExists::Error: Ignore
   *   - fields: (array, optional) The fields on the file entity to search for
   *     the input value. If omitted, and the input value is not a path nor url,
   *     there will be searched by file ID.
   *   - file_extensions: (array, optional) List of allowed file extensions.
   *     If provided, files with extensions not in this list will be rejected.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity, or NULL if no file could be resolved.
   *
   * @throws \RuntimeException
   *   If file.repository service is not available (for URL and Path inputs).
   * @throws \Drupal\feeds\Exception\DownloadException
   *   If file download fails (for URL inputs).
   * @throws \InvalidArgumentException
   *   If required options are missing.
   * @throws \Drupal\feeds\Exception\InvalidFileExtensionException
   *   If file extension is not allowed.
   */
  public function resolve(mixed $input, array $options = []): ?FileInterface;

}
