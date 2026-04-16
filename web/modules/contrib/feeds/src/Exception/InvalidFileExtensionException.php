<?php

namespace Drupal\feeds\Exception;

/**
 * Exception thrown when a file extension is not allowed.
 */
class InvalidFileExtensionException extends FeedsRuntimeException {

  /**
   * The file path or URL that has the invalid extension.
   *
   * @var string
   */
  protected $filePath;

  /**
   * The invalid extension.
   *
   * @var string
   */
  protected $extension;

  /**
   * The list of allowed extensions.
   *
   * @var array
   */
  protected $allowedExtensions;

  /**
   * Constructs an InvalidFileExtensionException.
   *
   * @param string $file_path
   *   The file path or URL that has the invalid extension.
   * @param string $extension
   *   The invalid extension.
   * @param array $allowed_extensions
   *   The list of allowed extensions.
   * @param \Throwable|null $previous
   *   (optional) The previous exception.
   */
  public function __construct(string $file_path, string $extension, array $allowed_extensions, ?\Throwable $previous = NULL) {
    $this->filePath = $file_path;
    $this->extension = $extension;
    $this->allowedExtensions = $allowed_extensions;

    $message = sprintf('The file extension "%s" is not allowed. Allowed extensions: %s', $extension, implode(', ', $allowed_extensions));
    parent::__construct($message, 0, $previous);
  }

  /**
   * Gets the file path or URL that has the invalid extension.
   *
   * @return string
   *   The file path or URL.
   */
  public function getFilePath(): string {
    return $this->filePath;
  }

  /**
   * Gets the invalid extension.
   *
   * @return string
   *   The extension.
   */
  public function getExtension(): string {
    return $this->extension;
  }

  /**
   * Gets the list of allowed extensions.
   *
   * @return array
   *   The allowed extensions.
   */
  public function getAllowedExtensions(): array {
    return $this->allowedExtensions;
  }

}
