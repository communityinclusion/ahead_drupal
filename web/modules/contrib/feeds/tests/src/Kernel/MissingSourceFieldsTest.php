<?php

namespace Drupal\Tests\feeds\Kernel;

use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests the behaviour for when a field is missing from the source.
 *
 * @group feeds
 */
class MissingSourceFieldsTest extends FeedsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpBodyField();
    $this->createFieldWithStorage('field_alpha', [
      'entity_type' => 'node',
      'bundle' => $this->nodeType->id(),
    ]);
    $this->createFieldWithStorage('field_beta', [
      'entity_type' => 'node',
      'bundle' => $this->nodeType->id(),
    ]);
  }

  /**
   * Test the various scenarios for clearing targets.
   *
   * @param array $processor_configuration
   *   Additional processor configuration to apply.
   * @param string $file
   *   The CSV file to use after the initial import.
   * @param array $expected
   *   Nest array of expected values, for each node updated.
   * @param string $message
   *   Message to display if assertion fails.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider provider
   */
  public function testMissingSource(array $processor_configuration, string $file, array $expected, string $message): void {
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'body' => 'body',
      'alpha' => 'alpha',
      'beta' => 'beta',
    ],
    [
      'processor_configuration' => $processor_configuration + [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'values' => [
          'type' => 'article',
        ],
      ],
      'mappings' => [
        [
          'target' => 'feeds_item',
          'map' => ['guid' => 'guid'],
          'unique' => ['guid' => TRUE],
        ],
        [
          'target' => 'title',
          'map' => ['value' => 'title'],
        ],
        [
          'target' => 'body',
          'map' => ['value' => 'body', 'summary' => 'summary'],
          'settings' => [
            'format' => 'plain_text',
            'language' => NULL,
          ],
        ],
        [
          'target' => 'field_alpha',
          'map' => ['value' => 'alpha'],
        ],
        [
          'target' => 'field_beta',
          'map' => ['value' => 'beta'],
        ],
      ],
    ]);

    // Import first feed.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content-with-summary.csv',
    ]);
    $feed->import();

    // Assert two created nodes.
    $this->assertNodeCount(2);

    $node = Node::load(1);
    $first_import_expected = [
      'title' => 'Lorem ipsum',
      'body' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.',
      'summary' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit.',
      'alpha' => 'Lorem',
      'beta' => 42,
    ];
    $this->assertNodeFields($node, $first_import_expected, $message);

    $node = Node::load(2);
    $first_import_expected = [
      'title' => 'Ut wisi enim ad minim veniam',
      'body' => 'Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat.',
      'summary' => 'Ut wisi enim ad minim veniam.',
      'alpha' => 'Ut wisi',
      'beta' => 32,
    ];
    $this->assertNodeFields($node, $first_import_expected, $message);

    // Now import a feed with missing or incomplete fields.
    $feed->setSource($this->resourcesPath() . '/csv/' . $file);
    $feed->save();
    $feed->import();

    // Assert two updated nodes.
    $this->assertNodeCount(2);


    $node = Node::load(1);
    $this->assertEquals('', $node->field_alpha->value, $message);
    $this->assertNodeFields($node, $expected[0], $message);

    $node = Node::load(2);
    $this->assertNodeFields($node, $expected[1], $message);
  }

  /**
   * Data provider for ::testMissingSource().
   *
   * Data is must provide the processor configuration, file to use in a second
   * import and the assertions to make against the update node in the format
   * expected by ::assertNodeFields.
   *
   * The files used are either:
   *  - content_missing_fields.csv
   *  - content_missing_fields-with-summary.csv
   *
   * In both files title is always provided, alpha is always provided as an
   * empty column to ensure empty columns do not get treated as missing columns,
   * beta is never supplied, body is never supplied, and summary is only
   * present in content_missing_fields-with-summary.csv which can be used to
   * test incomplete sources.
   */
  public function provider() {
    return [
      [
        'processor_configuration' => [],
        'file' => 'content_missing_fields.csv',
        'expected' => [
          [
            'title' => 'Lorem ipsum dolar',
            'body' => NULL,
            'summary' => NULL,
            'alpha' => '',
            'beta' => NULL,
          ],
          [
            'title' => 'Ut wisi enim ad minim',
            'body' => NULL,
            'summary' => NULL,
            'alpha' => '',
            'beta' => NULL,
          ],
        ],
        'message' => 'Missing fields should be treated as NULL values when using default configuration',
      ],
      [
        'processor_configuration' => [],
        'file' => 'content_missing_fields-with-summary.csv',
        'expected' => [
          [
            'title' => 'Lorem ipsum dolar',
            'body' => NULL,
            'summary' => 'Consectetuer adipiscing elit. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => NULL,
          ],
          [
            'title' => 'Ut wisi enim ad minim',
            'body' => NULL,
            'summary' => 'Ad minim veniam. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => NULL,
          ],
        ],
        'message' => 'Missing field columns should be treated as NULL values when using default configuration',
      ],
      [
        'processor_configuration' => [
          'skip_missing_source' => FALSE,
        ],
        'file' => 'content_missing_fields-with-summary.csv',
        'expected' => [
          [
            'title' => 'Lorem ipsum dolar',
            'body' => NULL,
            'summary' => 'Consectetuer adipiscing elit. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => NULL,
          ],
          [
            'title' => 'Ut wisi enim ad minim',
            'body' => NULL,
            'summary' => 'Ad minim veniam. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => NULL,
          ],
        ],
        'message' => 'Missing fields and field columns should be treated as NULL values',
      ],
      [
        'processor_configuration' => [
          'skip_missing_source' => TRUE,
        ],
        'file' => 'content_missing_fields-with-summary.csv',
        'expected' => [
          [
            'title' => 'Lorem ipsum dolar',
            'body' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.',
            'summary' => 'Consectetuer adipiscing elit. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => 42,
          ],
          [
            'title' => 'Ut wisi enim ad minim',
            'body' => 'Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat.',
            'summary' => 'Ad minim veniam. From content_missing_fields-with-summary.csv',
            'alpha' => '',
            'beta' => 32,
          ],
        ],
        'message' => 'Incomplete sources should be imported, and missing field columns retained.',
      ],
    ];
  }

  /**
   * Assert the expected values on the updated node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node updated by the second feed import.
   * @param array $expected
   *   Array of expected values, keyed as title, body, summary and beta. Note
   *   alpha is not provided as it should always be provided as an empty column.
   * @param string $message
   */
  protected function assertNodeFields(NodeInterface $node, array $expected, string $message): void {
    $this->assertEquals($expected['title'], $node->getTitle(), $message);
    $this->assertEquals($expected['body'], $node->body->value, $message);
    $this->assertEquals($expected['summary'], $node->body->summary, $message);
    $this->assertEquals($expected['alpha'], $node->field_alpha->value, $message);
    $this->assertEquals($expected['beta'], $node->field_beta->value, $message);
  }

}
