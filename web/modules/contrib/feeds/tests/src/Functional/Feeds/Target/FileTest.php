<?php

namespace Drupal\Tests\feeds\Functional\Feeds\Target;

use Drupal\Tests\feeds\Functional\FeedsBrowserTestBase;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\File
 * @group feeds
 */
class FileTest extends FeedsBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'feeds',
    'node',
    'user',
    'file',
    'feeds_test_files',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a file field.
    $this->createFieldWithStorage('field_file', [
      'type' => 'file',
      'field' => [
        'settings' => ['file_extensions' => 'png, gif, jpg, jpeg'],
      ],
    ]);
  }

  /**
   * Tests importing several files.
   */
  public function testImport() {
    // Create a feed type for importing nodes with files.
    $feed_type = $this->createFeedTypeForCsv([
      'title' => 'title',
      'timestamp' => 'timestamp',
      'file' => 'file',
    ], [
      'fetcher' => 'http',
      'fetcher_configuration' => [],
      'mappings' => [
        [
          'target' => 'title',
          'map' => ['value' => 'title'],
        ],
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file'],
          'settings' => [
            'reference_by' => 'filename',
            'existing' => '2',
            'autocreate' => FALSE,
          ],
        ],
      ],
    ]);

    // Create a feed and import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->getBaseUrl() . '/testing/feeds/files.csv',
    ]);
    $feed->import();

    // Assert that all files were imported.
    foreach ($this->getListOfTestFiles() as $file) {
      $file_path = $this->container->get('file_system')->realpath('public://' . date('Y-m') . '/' . $file);
      $this->assertFileExists($file_path);
    }
  }

  /**
   * Tests that configuring a file target on the mapping page works.
   */
  public function testConfigureTarget() {
    // Create a feed type.
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
    ]);

    // Go to the mapping page, and a target to 'field_file'.
    $edit = [
      'add_target' => 'field_file',
    ];
    $this->drupalGet('/admin/structure/feeds/manage/' . $feed_type->id() . '/mapping');
    $this->submitForm($edit, 'Save');

    // Check editing target configuration.
    $edit = [];
    $this->submitForm($edit, 'target-settings-2');

    // Assert that certain fields appear.
    $this->assertSession()->fieldExists('mappings[2][settings][reference_by]');
    $this->assertSession()->fieldExists('mappings[2][settings][existing]');

    // Assert that the autocreate field does not exist, since the file target
    // does not support that feature.
    $this->assertSession()->fieldNotExists('mappings[2][settings][autocreate]');
  }

  /**
   * Lists test files.
   */
  protected function getListOfTestFiles() {
    return [
      'tubing.jpeg',
      'foosball.jpeg',
      'attersee.jpeg',
      'hstreet.jpeg',
      'la fayette.jpeg',
      'attersee.JPG',
    ];
  }

  /**
   * Tests importing with an invalid URL (e.g., space in domain name).
   */
  public function testImportWithInvalidUrl() {
    // Create a feed type for importing nodes with files.
    $feed_type = $this->createFeedTypeForCsv([
      'title' => 'title',
      'file' => 'file',
    ], [
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'mappings' => [
        [
          'target' => 'title',
          'map' => ['value' => 'title'],
        ],
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file'],
          'settings' => [
            'reference_by' => 'filename',
            'existing' => '2',
            'autocreate' => FALSE,
          ],
        ],
      ],
    ]);

    // Create a CSV file with an invalid URL (space in domain).
    $invalid_url = 'http://example .com/file.jpg';
    $csv_dir = $this->container->get('file_system')->realpath('public://');
    $csv_file = $csv_dir . '/test_invalid_url.csv';
    $csv_content = "title,file\nTest Item," . $invalid_url . "\n";
    file_put_contents($csv_file, $csv_content);

    // Create feed pointing to this CSV.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => 'public://test_invalid_url.csv',
    ]);

    // Try to import.
    $this->batchImport($feed);

    // Assert that an understandable error message is displayed with the url
    // included.
    $this->assertSession()->pageTextContains('Download of http://example .com/file.jpg failed:');

    // Verify no file entities were created.
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $files = $file_storage->loadMultiple();
    $this->assertEmpty($files, 'No file entities should be created');

    // Clean up.
    if (file_exists($csv_file)) {
      unlink($csv_file);
    }
  }

  /**
   * Tests importing with a URL that results in a 404 error.
   */
  public function testImportWith404Url() {
    // Create a feed type for importing nodes with files.
    $feed_type = $this->createFeedTypeForCsv([
      'title' => 'title',
      'file' => 'file',
    ], [
      'fetcher' => 'http',
      'fetcher_configuration' => [],
      'mappings' => [
        [
          'target' => 'title',
          'map' => ['value' => 'title'],
        ],
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file'],
          'settings' => [
            'reference_by' => 'filename',
            'existing' => '2',
            'autocreate' => FALSE,
          ],
        ],
      ],
    ]);

    // The CSV source contains a file URL that will result in 404.
    // The CSV itself is served at /non-existent.csv via feeds_test_files
    // module.
    $csv_source_url = $this->getBaseUrl() . '/testing/feeds/files-404.csv';
    $expected_file_url = $this->getBaseUrl() . '/file-that-does-not-exist.jpg';

    // Create feed pointing to the CSV source.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $csv_source_url,
    ]);

    // Try to import.
    $this->batchImport($feed);

    // Assert that an understandable error message is displayed with the url
    // included.
    $this->assertSession()->pageTextContains("Download of $expected_file_url failed with code 404");

    // Verify no file entities were created.
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $files = $file_storage->loadMultiple();
    $this->assertEmpty($files, 'No file entities should be created');
  }

}
