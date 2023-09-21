<?php

namespace Drupal\vde_drush;

/**
 * Implements default format manipulator.
 */
class FormatManipulatorDefault implements FormatManipulatorInterface {

  /**
   * Exctracts header from rendered view chunk.
   *
   * @param string $content
   *   Content varible from where headers should be extracted.
   */
  protected function extractFooter(&$content) {
    // No default implementation here.
  }

  /**
   * Exctracts header from rendered view chunk.
   *
   * @param string $content
   *   Content varible from where headers should be extracted.
   */
  protected function extractHeader(&$content) {
    // No default implementation here.
  }

  /**
   * {@inheritdoc}
   */
  public function handle($output_file, &$content, $current_position, $total_items, $first_batch) {
    // If this is not the first batch then remove the header.
    if (!$first_batch) {
      $this->extractHeader($content);
    }

    // If current position is at the end of the data set, extract the footer
    // as well.
    if ($current_position < $total_items) {
      $this->extractFooter($content);
    }

    if ($first_batch) {
      // First batch overwrite file.
      return file_put_contents($output_file, $content);
    }
    else {
      // Subsequent batches append to the file.
      return file_put_contents($output_file, $content, FILE_APPEND);
    }
  }

}
