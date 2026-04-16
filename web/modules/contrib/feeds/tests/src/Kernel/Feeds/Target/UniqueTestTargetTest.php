<?php

namespace Drupal\Tests\feeds\Kernel\Feeds\Target;

use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\node\Entity\Node;

/**
 * @coversDefaultClass \Drupal\feeds_test_plugin\Feeds\Target\UniqueTestTarget
 * @group feeds
 */
class UniqueTestTargetTest extends FeedsKernelTestBase {

  /**
   * The feed type.
   *
   * @var \Drupal\feeds\FeedTypeInterface
   */
  protected $feedType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'text',
    'feeds',
    'feeds_test_plugin',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add body field.
    $this->setUpBodyField();

    // Create feed type with mapping to the custom target plugin
    // "unique_test_target" that defines a property called "code". Use that
    // property as unique.
    $this->feedType = $this->createFeedTypeForCsv([
      'alpha' => 'alpha',
      'body' => 'body',
    ], [
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => FALSE,
        'values' => [
          'type' => 'article',
        ],
      ],
      'mappings' => [
        [
          'target' => 'unique_test_target',
          'map' => ['code' => 'alpha'],
          'unique' => ['code' => TRUE],
          'settings' => [],
        ],
        [
          'target' => 'body',
          'map' => ['value' => 'body'],
          'settings' => [
            'format' => 'plain_text',
          ],
        ],
      ],
    ]);

    $this->feedType->save();
  }

  /**
   * Tests importing with unique target plugin.
   */
  public function testImportWithUniqueTarget() {
    // Import using the existing content.csv file.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();

    // Assert that 2 nodes were created.
    $this->assertNodeCount(2);

    // Check that nodes have the correct titles (set by our target plugin).
    // The target plugin stores the 'alpha' value in the title field.
    $nodes = Node::loadMultiple();
    $titles = [];
    foreach ($nodes as $node) {
      $titles[] = $node->getTitle();
    }

    // The content.csv has 'Lorem' and 'Ut wisi' in the alpha column.
    $this->assertContains('Lorem', $titles);
    $this->assertContains('Ut wisi', $titles);
  }

  /**
   * Tests updating existing entities with unique target.
   */
  public function testUpdateExistingWithUniqueTarget() {
    // Create a node with code 'Lorem' (from alpha column).
    $node = Node::create([
      'type' => 'article',
      'title' => 'Lorem',
      'uid' => 0,
    ]);
    $node->save();
    $original_nid = $node->id();

    // Import using the existing content.csv file.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();

    // Assert that only 2 nodes exist (the existing one was updated, plus one
    // new).
    $this->assertNodeCount(2);

    // Reload the node and verify it still has the correct title.
    $node = Node::load($original_nid);
    $this->assertEquals('Lorem', $node->getTitle());

    // Verify the body field has the expected value from the CSV.
    $expected_body = 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.';
    $this->assertEquals($expected_body, $node->body->value);
  }

  /**
   * Tests importing with non-unique target plugin (without getUniqueValue()).
   */
  public function testImportWithNonUniqueTarget() {
    // Create feed type with mapping to the custom target plugin
    // "non_unique_test_target" that defines a property called "code". Use that
    // property as unique.
    $feed_type = $this->createFeedTypeForCsv([
      'alpha' => 'alpha',
    ], [
      'processor_configuration' => [
        'update_existing' => ProcessorInterface::UPDATE_EXISTING,
        'authorize' => FALSE,
        'values' => [
          'type' => 'article',
        ],
      ],
      'mappings' => [
        [
          'target' => 'non_unique_test_target',
          'map' => ['code' => 'alpha'],
          'unique' => ['code' => TRUE],
          'settings' => [],
        ],
      ],
    ]);
    $feed_type->save();

    // Import using the existing content.csv file.
    // This should not fail even though getUniqueValue() is not implemented.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();

    // Assert that 2 nodes were created (import should succeed).
    $this->assertNodeCount(2);

    // Check that nodes have titles set by the target plugin.
    $nodes = Node::loadMultiple();
    $titles = [];
    foreach ($nodes as $node) {
      $titles[] = $node->getTitle();
    }

    // The content.csv has 'Lorem' and 'Ut wisi' in the alpha column.
    $this->assertContains('Lorem', $titles);
    $this->assertContains('Ut wisi', $titles);
  }

}
