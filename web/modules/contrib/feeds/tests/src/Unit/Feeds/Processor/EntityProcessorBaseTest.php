<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Processor;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\feeds\Feeds\Item\DynamicItem;
use Drupal\feeds\Feeds\Processor\EntityProcessorBase;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\feeds_test_plugin\Feeds\Processor\EntityTestProcessor;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Processor\EntityProcessorBase
 * @group feeds
 */
class EntityProcessorBaseTest extends FeedsUnitTestCase {

  /**
   * @var \Drupal\feeds_test_plugin\Feeds\Processor\EntityTestProcessor
   */
  protected EntityTestProcessor $processor;

  public function setUp(): void {
    parent::setUp();

    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getDefinition('foo')
      ->willReturn($entity_type->reveal());
    $entity_type_manager->getStorage('foo')
      ->willReturn($entity_storage->reveal());
    $entity_type_bundle_info = $this->prophesize(EntityTypeBundleInfoInterface::class);
    $language_manager = $this->prophesize(LanguageManagerInterface::class);
    $datetime = $this->prophesize(TimeInterface::class);
    $action_manager = $this->prophesize(PluginManagerInterface::class);
    $renderer = $this->prophesize(RendererInterface::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $database = $this->prophesize(Connection::class);
    $constraint_manager = $this->prophesize(ConstraintManager::class);
    $config_installer = $this->prophesize(ConfigInstallerInterface::class);
    $this->processor = new EntityTestProcessor(
      [
        'feed_type' => $this->getMockFeedType(),
      ],
      'entity:entity_test',
      [
        'entity_type' => 'foo',
      ],
      $entity_type_manager->reveal(),
      $entity_type_bundle_info->reveal(),
      $language_manager->reveal(),
      $datetime->reveal(),
      $action_manager->reveal(),
      $renderer->reveal(),
      $logger->reveal(),
      $database->reveal(),
      $constraint_manager->reveal(),
      $config_installer->reveal(),
    );
  }

  /**
   * @dataProvider provider
   */
  public function testGroupByProcessAction($skip_missing_source, $expected) {
    $field_mapping = [
      [
        'target' => 'feeds_item',
        'map' => ['guid' => 'guid'],
      ],
      [
        'target' => 'title',
        'map' => ['value' => 'title'],
      ],
      [
        'target' => 'field_missing',
        'map' => ['value' => 'missing'],
      ],
      [
        'target' => 'field_incomplete',
        'map' => [
          'value_one' => 'incomplete_one',
          'value_two' => 'incomplete_two',
        ],
      ],
    ];
    $this->processor->setConfiguration(['skip_missing_source' => $skip_missing_source]);

    $item = new DynamicItem();
    $item->fromArray([
      'guid' => 'foo',
      'title' => 'bar',
      'incomplete_one' => 'baz',
    ]);

    $grouped_mappings = $this->callProtectedMethod($this->processor, 'groupMappingsByAction', [$field_mapping, $item]);

    $this->assertEquals($expected, $grouped_mappings);

  }

  /**
   * Data provider for ::testGroupByProcessAction().
   */
  public function provider() {
    return [
      [
        FALSE,
        [
          EntityProcessorBase::FIELD_MAPPING_IMPORT => [
            0 => [
              'target' => 'feeds_item',
              'map' => ['guid' => 'guid'],
            ],
            1 => [
              'target' => 'title',
              'map' => ['value' => 'title'],
            ],
            2 => [
              'target' => 'field_missing',
              'map' => ['value' => 'missing'],
            ],
            3 => [
              'target' => 'field_incomplete',
              'map' => [
                'value_one' => 'incomplete_one',
                'value_two' => 'incomplete_two',
              ],
            ],
          ],
          EntityProcessorBase::FIELD_MAPPING_IMPORT_KEEP_MISSING_PROPERTIES => [],
          EntityProcessorBase::FIELD_MAPPING_SKIP => [],
        ],
      ],
      [
        TRUE,
        [
          EntityProcessorBase::FIELD_MAPPING_IMPORT => [
            0 => [
              'target' => 'feeds_item',
              'map' => ['guid' => 'guid'],
            ],
            1 => [
              'target' => 'title',
              'map' => ['value' => 'title'],
            ],
          ],
          EntityProcessorBase::FIELD_MAPPING_IMPORT_KEEP_MISSING_PROPERTIES => [
            3 => [
              'target' => 'field_incomplete',
              'map' => [
                'value_one' => 'incomplete_one',
                'value_two' => 'incomplete_two',
              ],
            ],
          ],
          EntityProcessorBase::FIELD_MAPPING_SKIP => [
            2 => [
              'target' => 'field_missing',
              'map' => ['value' => 'missing'],
            ],
          ],
        ],
      ],
    ];
  }

}
