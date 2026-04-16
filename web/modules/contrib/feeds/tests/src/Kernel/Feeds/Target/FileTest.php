<?php

namespace Drupal\Tests\feeds\Kernel\Feeds\Target;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Event\FeedsEvents;
use Drupal\feeds\Event\ParseEvent;
use Drupal\feeds\Feeds\Target\File;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\file\Entity\File as FileEntity;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Target\File
 * @group feeds
 */
class FileTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getTargetPluginClass() {
    return File::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetDefinition() {
    $method = $this->getMethod(File::class, 'prepareTarget')->getClosure();
    $field_definition_mock = $this->getMockFieldDefinition([
      'display_field' => 'false',
      'display_default' => 'false',
      'uri_scheme' => 'public',
      'target_type' => 'file',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'file_extensions' => 'pdf doc docx txt jpg jpeg ppt xls png',
      'max_filesize' => '',
      'description_field' => 'true',
      'handler' => 'default:file',
      'handler_settings' => [],
    ]);

    return $method($field_definition_mock);
  }

  /**
   * Data provider for testPrepareValue().
   */
  public static function dataProviderPrepareValue() {
    return [
      // Description.
      'description' => [
        'expected' => [
          'description' => 'mydescription',
          'display' => FALSE,
        ],
        'values' => [
          'description' => 'mydescription',
        ],
      ],
    ] + parent::dataProviderPrepareValue();
  }

  /**
   * Creates a feed type for file import testing.
   *
   * @param string $feed_type_id
   *   The feed type ID.
   *
   * @return \Drupal\feeds\FeedTypeInterface
   *   The created feed type.
   */
  protected function createFeedTypeForFileImport(string $feed_type_id): FeedTypeInterface {
    return $this->createFeedType([
      'id' => $feed_type_id,
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'parser' => 'csv',
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => 0,
        'values' => [
          'type' => 'article',
        ],
      ],
      'custom_sources' => [
        'guid' => [
          'label' => 'guid',
          'value' => 'guid',
          'machine_name' => 'guid',
        ],
        'title' => [
          'label' => 'title',
          'value' => 'title',
          'machine_name' => 'title',
        ],
        'file_path' => [
          'label' => 'file_path',
          'value' => 'file_path',
          'machine_name' => 'file_path',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file_path', 'description' => ''],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => 'ignore',
            'autocreate' => FALSE,
          ],
        ],
      ]),
    ]);
  }

  /**
   * Sets up an event listener to replace file path placeholder in CSV data.
   *
   * @param string $feed_type_id
   *   The feed type ID to listen for.
   * @param string $placeholder
   *   The placeholder value in the CSV (e.g., '[absolute path]').
   * @param string $actual_path
   *   The actual path/URI to replace the placeholder with.
   */
  protected function setupFilePathEventListener(string $feed_type_id, string $placeholder, string $actual_path): void {
    $this->container->get('event_dispatcher')->addListener(FeedsEvents::PARSE, function (ParseEvent $event) use ($feed_type_id, $placeholder, $actual_path) {
      if ($event->getFeed()->getType()->id() != $feed_type_id) {
        return;
      }

      /** @var \Drupal\feeds\Feeds\Item\ItemInterface $item */
      foreach ($event->getParserResult() as $item) {
        if ($item->get('file_path') == $placeholder) {
          $item->set('file_path', $actual_path);
        }
      }
    }, FeedsEvents::AFTER);
  }

  /**
   * Imports a feed from a CSV file.
   *
   * @param string $feed_type_id
   *   The feed type ID.
   * @param string $csv_file
   *   The CSV file name (relative to tests/resources/csv/).
   *
   * @return \Drupal\feeds\FeedInterface
   *   The imported feed.
   */
  protected function importFileFeed(string $feed_type_id, string $csv_file): FeedInterface {
    $feed = $this->createFeed($feed_type_id, [
      'source' => $this->resourcesPath() . '/csv/' . $csv_file,
    ]);
    $feed->import();
    return $feed;
  }

  /**
   * Tests if an import succeeds when mapping files, both full and empty.
   */
  public function testFullImportProcess() {
    // Add the file and image to test.
    $scheme = \Drupal::config('system.file')->get('default_scheme');
    $testImage = $this->writeData('<svg width="5" height="5"><circle cx="3" cy="3" r="2" stroke="black" stroke-width="1" fill="white" /></svg>', $scheme . '://testImage.svg', FileExists::Replace);
    $testFile = $this->writeData('feeds test file', $scheme . '://testFile.txt', FileExists::Replace);

    $feed_type = $this->createFeedType([
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'parser' => 'csv',
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => 0,
        'values' => [
          'type' => 'article',
        ],
      ],
      'custom_sources' => [
        'guid' => [
          'label' => 'guid',
          'value' => 'guid',
          'machine_name' => 'guid',
        ],
        'title' => [
          'label' => 'title',
          'value' => 'title',
          'machine_name' => 'title',
        ],
        'image' => [
          'label' => 'image',
          'value' => 'image',
          'machine_name' => 'image',
        ],
        'file' => [
          'label' => 'file',
          'value' => 'file',
          'machine_name' => 'file',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file', 'description' => ''],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => '2',
            'autocreate' => '1',
          ],
        ],
        [
          'target' => 'field_image',
          'map' => ['target_id' => 'image', 'alt' => '', 'title' => ''],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => '2',
            'autocreate' => '1',
          ],
        ],
      ]),
    ]);

    // Import first feed.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content_with_files.csv',
    ]);
    $feed->import();

    // Assert one created node.
    $this->assertNodeCount(1);

    // Check for the existence of the files.
    $node = Node::load(1);
    $expected_file_value = [
      [
        'target_id' => $testFile->id(),
        'display' => '0',
        'description' => '',
      ],
    ];
    $expected_image_value = [
      [
        'target_id' => $testImage->id(),
        'alt' => '',
        'title' => '',
        'width' => '',
        'height' => '',
      ],
    ];
    $this->assertEquals($expected_file_value, $node->get('field_file')->getValue());
    $this->assertEquals($expected_image_value, $node->get('field_image')->getValue());

    // Now import updated feed.
    $feed->setSource($this->resourcesPath() . '/csv/content_no_files.csv');
    $feed->save();
    $feed->import();

    // Reload the node and assert that the file and image have been removed.
    $node = Node::load(1);
    $this->assertEquals([], $node->get('field_file')->getValue());
    $this->assertEquals([], $node->get('field_image')->getValue());
  }

  /**
   * Tests importing a new file via absolute path.
   *
   * Flow: The CSV file to import gets manipulated via an event subscriber so
   * that it contains an absolute path to a file from the Feeds resources
   * directory. Imports the CSV file and checks if a node is imported with a
   * file entity. The name of the imported file should stay the same.
   *
   * @covers ::getFile
   */
  public function testImportNewFileViaAbsolutePath(): void {
    // Step 1: Locate source file for import.
    $source_file_path = $this->resourcesPath() . '/assets/text-file.txt';

    // Step 2: Create feed type.
    $feed_type = $this->createFeedTypeForFileImport('file_absolute_path_test');

    // Step 3: Set up event listener to set absolute path.
    $this->setupFilePathEventListener('file_absolute_path_test', '[absolute path]', $source_file_path);

    // Step 4: Import feed.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_absolute_path.csv');

    // Step 5: Assert results.
    $this->assertNodeCount(1);

    $node = Node::load(1);
    $this->assertNotNull($node);
    $this->assertEquals('Test Item with Absolute Path', $node->getTitle());

    $file_field_value = $node->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value);
    $file_id = $file_field_value[0]['target_id'];
    $this->assertNotEmpty($file_id);

    $file = FileEntity::load($file_id);
    $this->assertNotNull($file, 'File entity should exist');
    $this->assertStringContainsString('text-file.txt', $file->getFileUri());
    $this->assertFileExists($this->container->get('file_system')->realpath($file->getFileUri()));
  }

  /**
   * Tests importing a new file via public URI.
   *
   * Flow: Creates a file on the public filesystem. The CSV file to import gets
   * manipulated via an event subscriber so that it includes the uri of the just
   * created file. Imports the CSV file and checks if a node is imported with a
   * file entity on the public filesystem.
   *
   * @covers ::getFile
   */
  public function testImportNewFileViaPublicUri(): void {
    // Step 1: Create source file in public:// directory.
    $file_system = $this->container->get('file_system');
    $source_dir = 'public://source';
    $file_system->prepareDirectory($source_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $source_uri = $source_dir . '/test-public.txt';
    file_put_contents($this->container->get('file_system')->realpath($source_uri), 'Test content for public URI import');

    // Step 2: Create feed type.
    $feed_type = $this->createFeedTypeForFileImport('file_public_uri_test');

    // Step 3: Set up event listener to set public URI.
    $this->setupFilePathEventListener('file_public_uri_test', '[public uri]', $source_uri);

    // Step 4: Import feed.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_public_uri.csv');

    // Step 5: Assert results.
    $this->assertNodeCount(1);

    $node = Node::load(1);
    $this->assertNotNull($node);
    $this->assertEquals('Test Item with Public URI', $node->getTitle());

    $file_field_value = $node->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value);
    $file_id = $file_field_value[0]['target_id'];
    $this->assertNotEmpty($file_id);

    $file = FileEntity::load($file_id);
    $this->assertNotNull($file, 'File entity should exist');
    $this->assertStringStartsWith('public://', $file->getFileUri());
    $this->assertFileExists($this->container->get('file_system')->realpath($file->getFileUri()));
  }

  /**
   * Tests importing a file via private:// URI.
   *
   * Flow: Configures the file field to use the private filesystem. Creates an
   * user that may access content and sets this user as the current user.
   * Creates a file on the private filesystem. The CSV file to import gets
   * manipulated via an event subscriber so that it includes the uri of the just
   * created file. Imports the CSV file and checks if a node is imported with a
   * file entity on the private filesystem. The created user should be the owner
   * of the created file entity.
   *
   * @covers ::getFile
   */
  public function testImportFileViaPrivateUri(): void {
    // Step 1: Configure field_file to use private file system.
    $field_storage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->load('node.field_file');
    $field_storage->setSetting('uri_scheme', 'private');
    $field_storage->save();

    // Step 2: Create a test user and set as current user so files get the
    // correct owner.
    $this->installEntitySchema('user');
    $test_user = User::create([
      'uid' => 3,
      'name' => 'test_user',
      'mail' => 'test_user@example.com',
    ]);
    $test_user->save();
    $this->container->get('current_user')->setAccount($test_user);

    // Step 3: Grant permissions to access files. The authenticated user needs
    // permission to reference file entities in entity reference fields.
    $role = \Drupal::entityTypeManager()->getStorage('user_role')->load('authenticated');
    $role->grantPermission('access content');
    $role->save();

    // Step 4: Create source file in private:// directory.
    $file_system = $this->container->get('file_system');
    $source_dir = 'private://source';
    $file_system->prepareDirectory($source_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $source_uri = $source_dir . '/test-private.txt';
    file_put_contents($this->container->get('file_system')->realpath($source_uri), 'Test content for private URI import');

    // Step 5: Create feed type.
    $feed_type = $this->createFeedTypeForFileImport('file_private_uri_test');

    // Step 6: Set up event listener to set private URI.
    $this->setupFilePathEventListener('file_private_uri_test', '[private uri]', $source_uri);

    // Step 7: Import feed.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_private_uri.csv');

    // Step 8: Assert results.
    $this->assertNodeCount(1);

    $node = Node::load(1);
    $this->assertNotNull($node);
    $this->assertEquals('Test Item with Private URI', $node->getTitle());

    $file_field_value = $node->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value);
    $file_id = $file_field_value[0]['target_id'];
    $this->assertNotEmpty($file_id);

    $file = FileEntity::load($file_id);
    $this->assertNotNull($file, 'File entity should exist');
    $this->assertStringStartsWith('private://', $file->getFileUri());
    $this->assertEquals(\Drupal::currentUser()->id(), $file->getOwnerId(), 'File owner should be current user');
  }

  /**
   * Tests importing with non-existent file path.
   *
   * Flow: Tries to import a CSV file that references a file that does not
   * exist. Import should result into an error message and no file entities
   * should be created.
   *
   * @covers ::getFile
   */
  public function testImportWithNonexistentPath(): void {
    // Step 1: Create feed type.
    $feed_type = $this->createFeedTypeForFileImport('file_nonexistent_path_test');

    // Step 2: Set up event listener to set non-existent path.
    $nonexistent_path = $this->resourcesPath() . '/assets/does-not-exist.txt';
    $this->setupFilePathEventListener('file_nonexistent_path_test', '[nonexistent path]', $nonexistent_path);

    // Step 3: Import feed.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_nonexistent_path.csv');

    // Check that error message contains the path.
    $messages = \Drupal::messenger()->all();
    $this->assertStringContainsString($nonexistent_path, (string) $messages['error'][0], 'Error message should contain the file path');
    $this->assertStringContainsString('There was an error resolving the file', (string) $messages['error'][0], 'Error message should indicate file resolution error');

    // Verify no file entities were created.
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $files = $file_storage->loadMultiple();
    $this->assertEmpty($files, 'No file entities should be created');

    // Clear the logged messages so no failure is reported on tear down.
    $this->logger->clearMessages();
  }

  /**
   * Tests importing with invalid file extension.
   *
   * Flow: Tries to import a CSV file that references a JPEG file, but the file
   * field is configured to only accept text files. Import should result into an
   * error message and no file entities should be created.
   *
   * @covers ::getFile
   */
  public function testImportWithInvalidExtension(): void {
    // Step 1: Create source file with invalid extension.
    $source_file_path = $this->resourcesPath() . '/assets/tubing.jpeg';

    // Step 2: Create feed type.
    $feed_type = $this->createFeedTypeForFileImport('file_invalid_extension_test');

    // Step 3: Set up event listener to set invalid extension path.
    $this->setupFilePathEventListener('file_invalid_extension_test', '[invalid extension path]', $source_file_path);

    // Step 4: Import feed.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_invalid_extension.csv');

    // Check that error message contains both path and extension.
    $messages = \Drupal::messenger()->all();
    $error_message = (string) $messages['error'][0];
    $this->assertStringContainsString($source_file_path, $error_message, 'Error message should contain the file path');
    $this->assertStringContainsString('jpeg', $error_message, 'Error message should contain the invalid extension');
    $this->assertStringContainsString('failed to save because the extension', $error_message, 'Error message should indicate extension validation failure');

    // Verify no file entities were created.
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $files = $file_storage->loadMultiple();
    $this->assertEmpty($files, 'No file entities should be created');

    // Clear the logged messages so no failure is reported on tear down.
    $this->logger->clearMessages();
  }

  /**
   * Tests repeated import of the same file.
   *
   * Flow: Import a CSV file with two items that both reference the same file
   * using a path. Verifies that only one file entity gets created and that both
   * imported nodes reference the same file entity.
   * Also tests that on a subsequent import, where an update is forced by
   * enabling the 'skip_hash_check' option, no new file entities get created,
   * because the file to import is exactly the same as the one on the
   * destination.
   *
   * @covers ::getFile
   */
  public function testRepeatedImportOfSameFile(): void {
    // Step 1: Locate source file for import.
    $source_file_path = $this->resourcesPath() . '/assets/text-file.txt';

    // Step 2: Create feed type with skip_hash_check enabled.
    $feed_type = $this->createFeedType([
      'id' => 'file_repeated_test',
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'parser' => 'csv',
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => 0,
        'skip_hash_check' => TRUE,
        'values' => [
          'type' => 'article',
        ],
      ],
      'custom_sources' => [
        'guid' => [
          'label' => 'guid',
          'value' => 'guid',
          'machine_name' => 'guid',
        ],
        'title' => [
          'label' => 'title',
          'value' => 'title',
          'machine_name' => 'title',
        ],
        'file_path' => [
          'label' => 'file_path',
          'value' => 'file_path',
          'machine_name' => 'file_path',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file_path', 'description' => ''],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => 'ignore',
            'autocreate' => FALSE,
          ],
        ],
      ]),
    ]);

    // Step 3: Set up event listener to set file path.
    $this->setupFilePathEventListener('file_repeated_test', '[file path]', $source_file_path);

    // Step 4: First import.
    $feed = $this->importFileFeed($feed_type->id(), 'file_import_repeated.csv');

    // Step 5: Assert first import results.
    $this->assertNodeCount(2);

    $node1 = Node::load(1);
    $node2 = Node::load(2);
    $this->assertNotNull($node1);
    $this->assertNotNull($node2);

    $file_field_value1 = $node1->get('field_file')->getValue();
    $file_field_value2 = $node2->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value1);
    $this->assertCount(1, $file_field_value2);

    $file_id1 = $file_field_value1[0]['target_id'];
    $file_id2 = $file_field_value2[0]['target_id'];
    $this->assertEquals($file_id1, $file_id2, 'Both nodes should reference the same file entity');

    // Step 6: Count file entities after first import.
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $files_after_first = $file_storage->loadMultiple();
    $file_count_after_first = count($files_after_first);
    $this->assertEquals(1, $file_count_after_first, 'Only one file entity should exist after first import');

    // Step 7: Second import.
    $feed->import();

    // Step 8: Assert second import results.
    $this->assertNodeCount(2, 'Node count should remain the same after second import');

    $files_after_second = $file_storage->loadMultiple();
    $file_count_after_second = count($files_after_second);
    $this->assertEquals(1, $file_count_after_second, 'Only one file entity should exist after second import');
    $this->assertEquals($file_count_after_first, $file_count_after_second, 'File entity count should not change after second import');

    // Verify both nodes still reference the same file.
    $node1 = $this->reloadEntity($node1);
    $node2 = $this->reloadEntity($node2);
    $file_field_value1 = $node1->get('field_file')->getValue();
    $file_field_value2 = $node2->get('field_file')->getValue();
    $this->assertEquals($file_field_value1[0]['target_id'], $file_field_value2[0]['target_id'], 'Both nodes should still reference the same file entity');
  }

  /**
   * Tests file lookup by feeds_item.guid.
   *
   * Flow: Creates a file entity with a feeds_item field containing a guid
   * value. Creates a feed type configured to reference files by
   * feeds_item.guid. Imports two items: one with a guid matching the existing
   * file, one with a new guid. Verifies that the first item references the
   * existing file, and the second item references a newly created file.
   * Because the second item needs to import a new file, the source value of the
   * second item is a path to a file.
   *
   * @covers ::getFile
   * @covers ::findEntity
   */
  public function testFileLookupByFeedsItem() {
    // Step 1: Add a feeds_item field to the File entity.
    // File entities don't have bundles, so we use the entity type as the
    // bundle.
    $this->createFieldWithStorage('feeds_item', [
      'entity_type' => 'file',
      'bundle' => 'file',
      'type' => 'feeds_item',
      'label' => 'Feeds item',
    ]);

    // Step 2: Create a file entity with a feeds_item.guid value.
    // First create a feed to reference in the feeds_item field (target_id is
    // required).
    $feed = $this->createFeed($this->createFeedType()->id());

    $scheme = \Drupal::config('system.file')->get('default_scheme');
    $existing_file = $this->writeData('existing file content', $scheme . '://existing.txt', FileExists::Replace);
    $existing_guid = 'existing-file-guid-123';
    $existing_file->set('feeds_item', [
      'target_id' => $feed->id(),
      'imported' => 0,
      'guid' => $existing_guid,
      'hash' => '',
    ]);
    $existing_file->save();

    // Step 3: Create a feed type with mapping to field_file.
    $feed_type = $this->createFeedType([
      'id' => 'file_feeds_item_test',
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'parser' => 'csv',
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => 0,
        'values' => [
          'type' => 'article',
        ],
      ],
      'custom_sources' => [
        'guid' => [
          'label' => 'guid',
          'value' => 'guid',
          'machine_name' => 'guid',
        ],
        'title' => [
          'label' => 'title',
          'value' => 'title',
          'machine_name' => 'title',
        ],
        'file_guid' => [
          'label' => 'file_guid',
          'value' => 'file_guid',
          'machine_name' => 'file_guid',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file_guid', 'description' => ''],
          'settings' => [
            'reference_by' => 'feeds_item',
            'feeds_item' => 'guid',
            'existing' => 'ignore',
            'autocreate' => FALSE,
          ],
        ],
      ]),
    ]);

    // Step 4: Set up event listener to alter parsed CSV data with dynamic
    // values. This is used to set the file_guid value of the second item to a
    // file path. This way for the second item a new file can get imported.
    $new_file_path = $this->resourcesPath() . '/assets/text-file.txt';

    // Add event listener to alter parsed items with dynamic file_guid value.
    $this->container->get('event_dispatcher')->addListener(FeedsEvents::PARSE, function (ParseEvent $event) use ($new_file_path) {
      if ($event->getFeed()->getType()->id() != 'file_feeds_item_test') {
        return;
      }

      /** @var \Drupal\feeds\Feeds\Item\ItemInterface $item */
      foreach ($event->getParserResult() as $item) {
        // Alter the file_guid value for the second item to use the URL.
        if ($item->get('guid') == 'item-2') {
          // Second item should use the absolute path to a file to be imported
          // as a new file entity.
          $item->set('file_guid', $new_file_path);
        }
      }
    }, FeedsEvents::AFTER);

    // Step 5: Import the feed using the existing CSV file.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/files_by_feeds_item.csv',
    ]);
    $feed->import();

    // Step 6: Assert results.
    $this->assertNodeCount(2);

    // First node should reference the existing file.
    $node1 = Node::load(1);
    $this->assertNotNull($node1);
    $this->assertEquals('First Item', $node1->getTitle());
    $file_field_value = $node1->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value);
    $this->assertEquals($existing_file->id(), $file_field_value[0]['target_id'], 'First item should reference the existing file');

    // Second node should reference a newly created file.
    $node2 = Node::load(2);
    $this->assertNotNull($node2);
    $this->assertEquals('Second Item', $node2->getTitle());
    $file_field_value2 = $node2->get('field_file')->getValue();
    $this->assertCount(1, $file_field_value2);
    $this->assertNotEquals($existing_file->id(), $file_field_value2[0]['target_id'], 'Second item should reference a different file');
    $new_file = FileEntity::load($file_field_value2[0]['target_id']);
    $this->assertNotNull($new_file, 'New file entity should exist');
    $this->assertStringContainsString('text-file.txt', $new_file->getFileUri());
  }

  /**
   * Tests that overriding getFileName() triggers the legacy code path.
   *
   * @covers \Drupal\feeds\Feeds\Target\File::hasOverriddenLegacyMethods
   * @covers \Drupal\feeds\Feeds\Target\File::getFileLegacy
   * @group legacy
   */
  public function testGetFileNameOverrideUsesLegacyPath() {
    FileGetFileNameOverrideTestPlugin::$lastFileName = '';

    $this->client->request('GET', $this->resourcesUrl() . '/assets/attersee.jpeg', Argument::any())
      ->willReturn(new Response(200, [], file_get_contents($this->resourcesPath() . '/assets/attersee.jpeg')));

    $plugin = $this->getTargetPlugin(FileGetFileNameOverrideTestPlugin::class);
    $method = $this->getProtectedClosure($plugin, 'prepareValue');
    $values = [
      'target_id' => $this->resourcesUrl() . '/assets/attersee.jpeg',
      'description' => '',
    ];
    $method(0, $values);

    $this->assertArrayHasKey('target_id', $values);
    $this->assertIsInt($values['target_id']);
    $this->assertStringContainsString('override-getfilename', FileGetFileNameOverrideTestPlugin::$lastFileName);
  }

  /**
   * Tests that overriding getContent() triggers the legacy code path.
   *
   * @covers \Drupal\feeds\Feeds\Target\File::hasOverriddenLegacyMethods
   * @covers \Drupal\feeds\Feeds\Target\File::getFileLegacy
   * @group legacy
   */
  public function testGetContentOverrideUsesLegacyPath() {
    FileGetContentOverrideTestPlugin::$getContentWasCalled = FALSE;

    $plugin = $this->getTargetPlugin(FileGetContentOverrideTestPlugin::class);
    $method = $this->getProtectedClosure($plugin, 'prepareValue');
    $values = [
      'target_id' => $this->resourcesUrl() . '/assets/attersee.jpeg',
      'description' => '',
    ];
    $method(0, $values);

    $this->assertArrayHasKey('target_id', $values);
    $this->assertIsInt($values['target_id']);
    $this->assertTrue(FileGetContentOverrideTestPlugin::$getContentWasCalled);
  }

  /**
   * Tests that overriding writeData() triggers the legacy code path.
   *
   * @covers \Drupal\feeds\Feeds\Target\File::hasOverriddenLegacyMethods
   * @covers \Drupal\feeds\Feeds\Target\File::getFileLegacy
   * @group legacy
   */
  public function testWriteDataOverrideUsesLegacyPath() {
    FileWriteDataOverrideTestPlugin::$writeDataWasCalled = FALSE;

    $this->client->request('GET', $this->resourcesUrl() . '/assets/attersee.jpeg', Argument::any())
      ->willReturn(new Response(200, [], file_get_contents($this->resourcesPath() . '/assets/attersee.jpeg')));

    $plugin = $this->getTargetPlugin(FileWriteDataOverrideTestPlugin::class);
    $method = $this->getProtectedClosure($plugin, 'prepareValue');
    $values = [
      'target_id' => $this->resourcesUrl() . '/assets/attersee.jpeg',
      'description' => '',
    ];
    $method(0, $values);

    $this->assertArrayHasKey('target_id', $values);
    $this->assertIsInt($values['target_id']);
    $this->assertTrue(FileWriteDataOverrideTestPlugin::$writeDataWasCalled);
  }

}

/**
 * Test plugin that overrides getFileName() to use a custom filename.
 */
class FileGetFileNameOverrideTestPlugin extends File {

  /**
   * The last filename returned by getFileName().
   *
   * @var string
   */
  public static $lastFileName = '';

  /**
   * {@inheritdoc}
   */
  protected function getFileName($url) {
    $filename = parent::getFileName($url);
    self::$lastFileName = 'override-getfilename-' . $filename;

    return 'override-getfilename.txt';
  }

}

/**
 * Test plugin that overrides getContent() to return custom content.
 */
class FileGetContentOverrideTestPlugin extends File {

  /**
   * Whether getContent() was called.
   *
   * @var bool
   */
  public static $getContentWasCalled = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function getContent($url) {
    self::$getContentWasCalled = TRUE;
    $path = dirname(__DIR__, 4) . '/resources/assets/attersee.jpeg';

    return file_get_contents($path);
  }

}

/**
 * Test plugin that overrides writeData() to track that it was called.
 */
class FileWriteDataOverrideTestPlugin extends File {

  /**
   * Whether writeData() was called.
   *
   * @var bool
   */
  public static $writeDataWasCalled = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function writeData($data, $destination = NULL, $replace = FileExists::Rename) {
    self::$writeDataWasCalled = TRUE;

    return parent::writeData($data, $destination, $replace);
  }

}
