<?php

namespace Drupal\Tests\feeds\Kernel\Feeds\Target;

use Drupal\Core\File\FileSystemInterface;
use Drupal\feeds\Feeds\Target\File;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\node\Entity\Node;

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
   * Tests if an import succeeds when mapping files, both full and empty.
   */
  public function testFullImportProcess() {
    // Add the file and image to test.
    $scheme = \Drupal::config('system.file')->get('default_scheme');
    $testImage = $this->writeData('<svg width="5" height="5"><circle cx="3" cy="3" r="2" stroke="black" stroke-width="1" fill="white" /></svg>', $scheme . '://testImage.svg', FileSystemInterface::EXISTS_REPLACE);
    $testFile = $this->writeData('feeds test file', $scheme . '://testFile.txt', FileSystemInterface::EXISTS_REPLACE);

    $feed_type = $this->createFeedTypeForCsvWithFiles();

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
        'description' => 'bar',
      ],
    ];
    $expected_image_value = [
      [
        'target_id' => $testImage->id(),
        'alt' => 'foo',
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
   * Tests missing file ids various reference by settings.
   *
   * @dataProvider dataProviderReferenceBy
   */
  public function testIncompleteSourcesReferenceBy($reference_by) {
    // Add the file and image to test.
    $scheme = \Drupal::config('system.file')->get('default_scheme');
    $testImage = $this->writeData('<svg width="5" height="5"><circle cx="3" cy="3" r="2" stroke="black" stroke-width="1" fill="white" /></svg>', $scheme . '://testImage.svg', FileSystemInterface::EXISTS_REPLACE);
    $testFile = $this->writeData('feeds test file', $scheme . '://testFile.txt', FileSystemInterface::EXISTS_REPLACE);

    $feed_type = $this->createFeedTypeForCsvWithFiles(['skip_missing_source' => TRUE]);

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
        'description' => 'bar',
      ],
    ];
    $expected_image_value = [
      [
        'target_id' => $testImage->id(),
        'alt' => 'foo',
        'title' => '',
        'width' => '',
        'height' => '',
      ],
    ];
    $this->assertEquals($expected_file_value, $node->get('field_file')->getValue());
    $this->assertEquals($expected_image_value, $node->get('field_image')->getValue());

    // Change the reference by values.
    $mappings = $feed_type->getMappings();
    $mappings['0']['settings']['reference_by'] = $reference_by;
    $mappings['0']['settings']['reference_by'] = $reference_by;
    $feed_type->setMappings($mappings);
    $feed_type->save();

    // Now import updated feed with missing file ids.
    $feed = $this->reloadEntity($feed);
    $feed->setSource($this->resourcesPath() . '/csv/content_with_files_missing_ids.csv');
    $feed->save();
    $feed->import();

    // Reload the node and assert that the description and alt have changed.
    $node = Node::load(1);
    $expected_file_value = [
      [
        'target_id' => $testFile->id(),
        'display' => '0',
        'description' => 'zap',
      ],
    ];
    $expected_image_value = [
      [
        'target_id' => $testImage->id(),
        'alt' => 'zip',
        'title' => '',
        'width' => '',
        'height' => '',
      ],
    ];
    $this->assertEquals($expected_file_value, $node->get('field_file')->getValue());
    $this->assertEquals($expected_image_value, $node->get('field_image')->getValue());
  }

  /**
   * Data provider for testIncompleteSourcesReferenceBy
   *
   * @return array[]
   *   Property to use in reference by config.
   */
  public function dataProviderReferenceBy() {
     return [['fid'], ['uuid'], ['filename']];
  }

  /**
   * Create a feed type for use with CSV  content_with_files.csv
   *
   * @return \Drupal\feeds\FeedTypeInterface
   */
  protected function createFeedTypeForCsvWithFiles(array $processor_config = []) {
    return $this->createFeedType([
      'fetcher' => 'directory',
      'fetcher_configuration' => [
        'allowed_extensions' => 'csv',
      ],
      'parser' => 'csv',
      'processor_configuration' => $processor_config + [
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
        'alt' => [
          'label' => 'alt',
          'value' => 'alt',
          'machine_name' => 'alt',
        ],
        'file' => [
          'label' => 'file',
          'value' => 'file',
          'machine_name' => 'file',
        ],
        'description' => [
          'label' => 'description',
          'value' => 'description',
          'machine_name' => 'description',
        ],
      ],
      'mappings' => array_merge($this->getDefaultMappings(), [
        [
          'target' => 'field_file',
          'map' => ['target_id' => 'file', 'description' => 'description'],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => '2',
            'autocreate' => '1',
          ],
        ],
        [
          'target' => 'field_image',
          'map' => ['target_id' => 'image', 'alt' => 'alt', 'title' => ''],
          'settings' => [
            'reference_by' => 'fid',
            'existing' => '2',
            'autocreate' => '1',
          ],
        ],
      ]),
    ]);
  }

}
