<?php

namespace Drupal\feeds\Exception;

/**
 * Exception thrown when a file download fails.
 */
class DownloadException extends FeedsRuntimeException {

  /**
   * The URL that failed to download.
   *
   * @var string
   */
  protected $url;

  /**
   * The HTTP status code, if available.
   *
   * @var int|null
   */
  protected $statusCode;

  /**
   * The error message, if available.
   *
   * @var string|null
   */
  protected $errorMessage;

  /**
   * Constructs a DownloadException.
   *
   * @param string $url
   *   The URL that failed to download.
   * @param int|null $status_code
   *   (optional) The HTTP status code, if the request returned an error status.
   * @param string|null $error_message
   *   (optional) The error message, if the request threw an exception.
   * @param \Throwable|null $previous
   *   (optional) The previous exception.
   */
  public function __construct(string $url, ?int $status_code = NULL, ?string $error_message = NULL, ?\Throwable $previous = NULL) {
    $this->url = $url;
    $this->statusCode = $status_code;
    $this->errorMessage = $error_message;

    // Build the exception message.
    if ($status_code !== NULL) {
      $message = sprintf('Download of %s failed with code %d.', $url, $status_code);
    }
    elseif ($error_message !== NULL) {
      $message = sprintf('Download of %s failed: %s', $url, $error_message);
    }
    else {
      $message = sprintf('Download of %s failed.', $url);
    }

    parent::__construct($message, 0, $previous);
  }

  /**
   * Gets the URL that failed to download.
   *
   * @return string
   *   The URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Gets the HTTP status code, if available.
   *
   * @return int|null
   *   The status code, or NULL if not available.
   */
  public function getStatusCode(): ?int {
    return $this->statusCode;
  }

  /**
   * Gets the error message, if available.
   *
   * @return string|null
   *   The error message, or NULL if not available.
   */
  public function getErrorMessage(): ?string {
    return $this->errorMessage;
  }

}
