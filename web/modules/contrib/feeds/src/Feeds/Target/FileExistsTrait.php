<?php

namespace Drupal\feeds\Feeds\Target;

use Drupal\Core\File\FileExists;

/**
 * Trait for handling FileExists enum conversion with backward compatibility.
 *
 * This trait provides methods for normalizing configuration values that may
 * contain legacy integer constants or string representations to FileExists enum
 * instances.
 */
trait FileExistsTrait {

  /**
   * Normalizes the 'existing' configuration value to a FileExists enum.
   *
   * Handles backward compatibility by converting legacy integer constants
   * to the FileExists enum.
   *
   * @param mixed $value
   *   The configuration value, which may be:
   *   - A FileExists enum instance
   *   - A string (enum name or string representation of integer)
   *   - An integer (legacy constant value)
   *
   * @return \Drupal\Core\File\FileExists
   *   The FileExists enum instance.
   */
  protected function normalizeFileExists($value): FileExists {
    // If already a FileExists enum, return it.
    if ($value instanceof FileExists) {
      return $value;
    }

    // If it's a string, try to match it to an enum case name.
    if (is_string($value)) {
      // Check if it's a numeric string (legacy integer).
      if (is_numeric($value)) {
        return $this->mapLegacyIntToFileExists((int) $value);
      }
      // Try to match against enum case names.
      return match (strtolower($value)) {
        'rename' => FileExists::Rename,
        'replace' => FileExists::Replace,
        'ignore' => FileExists::Error,
        default => FileExists::Error,
      };
    }

    // If it's an integer (legacy constant), convert it.
    if (is_int($value)) {
      return $this->mapLegacyIntToFileExists($value);
    }

    // Default to Error if we can't determine the value.
    return FileExists::Error;
  }

  /**
   * Maps legacy integer constants to FileExists enum cases.
   *
   * @param int $legacy_int
   *   The legacy constant value:
   *   - 0 = FileSystemInterface::EXISTS_RENAME -> FileExists::Rename
   *   - 1 = FileSystemInterface::EXISTS_REPLACE -> FileExists::Replace
   *   - 2 = FileSystemInterface::EXISTS_ERROR -> FileExists::Error.
   *
   * @return \Drupal\Core\File\FileExists
   *   The corresponding FileExists enum case.
   */
  protected function mapLegacyIntToFileExists(int $legacy_int): FileExists {
    return match ($legacy_int) {
      0 => FileExists::Rename,
      2 => FileExists::Error,
      default => FileExists::Replace,
    };
  }

  /**
   * Gets the case name of a FileExists enum as a string.
   *
   * @param \Drupal\Core\File\FileExists $enum
   *   The FileExists enum instance.
   *
   * @return string
   *   The case name (e.g., "rename", "replace", "ignore").
   */
  protected function getFileExistsName(FileExists $enum): string {
    return match ($enum) {
      FileExists::Rename => 'rename',
      FileExists::Replace => 'replace',
      FileExists::Error => 'ignore',
    };
  }

}
