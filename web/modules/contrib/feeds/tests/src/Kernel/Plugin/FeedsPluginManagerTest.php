<?php

namespace Drupal\Tests\feeds\Kernel\Plugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds_test_plugin\Feeds\CustomSource\AnnotatedTestCustomSource;
use Drupal\feeds_test_plugin\Feeds\CustomSource\AttributeTestCustomSource;
use Drupal\feeds_test_plugin\Feeds\Fetcher\AnnotatedTestFetcher;
use Drupal\feeds_test_plugin\Feeds\Fetcher\AttributeTestFetcher;
use Drupal\feeds_test_plugin\Feeds\Parser\AnnotatedTestParser;
use Drupal\feeds_test_plugin\Feeds\Parser\AttributeTestParser;
use Drupal\feeds_test_plugin\Feeds\Processor\AnnotatedTestProcessor;
use Drupal\feeds_test_plugin\Feeds\Processor\AttributeTestProcessor;
use Drupal\feeds_test_plugin\Feeds\Source\AnnotatedTestSource;
use Drupal\feeds_test_plugin\Feeds\Source\AttributeTestSource;
use Drupal\feeds_test_plugin\Feeds\Target\AnnotatedTestTarget;
use Drupal\feeds_test_plugin\Feeds\Target\AttributeTestTarget;
use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;

/**
 * Tests FeedsPluginManager with both annotated and attributed plugins.
 *
 * @group feeds
 */
class FeedsPluginManagerTest extends FeedsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'feeds',
    'feeds_test_plugin',
  ];

  /**
   * Tests if FeedsFetcher plugins defined with annotation can be found.
   */
  public function testFindAnnotatedFetcherPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.fetcher')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_fetcher',
      'title' => new TranslatableMarkup('Annotated Test Fetcher'),
      'description' => new TranslatableMarkup('A test fetcher plugin using annotations.'),
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestFetcher::class,
      'plugin_type' => 'fetcher',
      'form' => [],
    ];

    $actual = $definitions['annotated_test_fetcher'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.fetcher')->hasDefinition('annotated_test_fetcher'));
  }

  /**
   * Tests if FeedsFetcher plugins defined with attributes can be found.
   */
  public function testFindAttributedFetcherPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.fetcher')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_fetcher',
      'title' => new TranslatableMarkup('Attribute Test Fetcher'),
      'description' => new TranslatableMarkup('A test fetcher plugin using attributes.'),
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestFetcher::class,
      'plugin_type' => 'fetcher',
      'form' => [],
    ];

    $actual = $definitions['attribute_test_fetcher'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.fetcher')->hasDefinition('attribute_test_fetcher'));
  }

  /**
   * Tests if FeedsParser plugins defined with annotation can be found.
   */
  public function testFindAnnotatedParserPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.parser')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_parser',
      'title' => new TranslatableMarkup('Annotated Test Parser'),
      'description' => new TranslatableMarkup('A test parser plugin using annotations.'),
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestParser::class,
      'plugin_type' => 'parser',
      'form' => [],
    ];

    $actual = $definitions['annotated_test_parser'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.parser')->hasDefinition('annotated_test_parser'));
  }

  /**
   * Tests if FeedsParser plugins defined with attributes can be found.
   */
  public function testFindAttributedParserPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.parser')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_parser',
      'title' => new TranslatableMarkup('Attribute Test Parser'),
      'description' => new TranslatableMarkup('A test parser plugin using attributes.'),
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestParser::class,
      'plugin_type' => 'parser',
      'form' => [],
    ];

    $actual = $definitions['attribute_test_parser'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.parser')->hasDefinition('attribute_test_parser'));
  }

  /**
   * Tests if FeedsProcessor plugins defined with annotation can be found.
   */
  public function testFindAnnotatedProcessorPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.processor')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_processor',
      'title' => new TranslatableMarkup('Annotated Test Processor'),
      'description' => new TranslatableMarkup('A test processor plugin using annotations.'),
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestProcessor::class,
      'plugin_type' => 'processor',
      'form' => [],
    ];

    $actual = $definitions['annotated_test_processor'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.processor')->hasDefinition('annotated_test_processor'));
  }

  /**
   * Tests if FeedsProcessor plugins defined with attributes can be found.
   */
  public function testFindAttributedProcessorPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.processor')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_processor',
      'title' => new TranslatableMarkup('Attribute Test Processor'),
      'description' => new TranslatableMarkup('A test processor plugin using attributes.'),
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestProcessor::class,
      'plugin_type' => 'processor',
      'form' => [],
    ];

    $actual = $definitions['attribute_test_processor'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.processor')->hasDefinition('attribute_test_processor'));
  }

  /**
   * Tests if FeedsSource plugins defined with annotation can be found.
   */
  public function testFindAnnotatedSourcePlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.source')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_source',
      'title' => new TranslatableMarkup('Annotated Test Source'),
      'description' => new TranslatableMarkup('A test source plugin using annotations.'),
      'field_types' => ['text'],
      'category' => new TranslatableMarkup('Test'),
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestSource::class,
      'plugin_type' => 'source',
    ];

    $actual = $definitions['annotated_test_source'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.source')->hasDefinition('annotated_test_source'));
  }

  /**
   * Tests if FeedsSource plugins defined with attributes can be found.
   */
  public function testFindAttributedSourcePlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.source')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_source',
      'title' => new TranslatableMarkup('Attribute Test Source'),
      'description' => new TranslatableMarkup('A test source plugin using attributes.'),
      'field_types' => ['text'],
      'category' => new TranslatableMarkup('Test'),
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestSource::class,
      'plugin_type' => 'source',
    ];

    $actual = $definitions['attribute_test_source'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.source')->hasDefinition('attribute_test_source'));
  }

  /**
   * Tests if FeedsCustomSource plugins defined with annotation can be found.
   */
  public function testFindAnnotatedCustomSourcePlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.custom_source')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_custom_source',
      'title' => new TranslatableMarkup('Annotated Test Custom Source'),
      'description' => new TranslatableMarkup('A test custom source plugin using annotations.'),
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestCustomSource::class,
      'plugin_type' => 'custom_source',
      'form' => [
        'configuration' => AnnotatedTestCustomSource::class,
      ],
    ];

    $actual = $definitions['annotated_test_custom_source'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.custom_source')->hasDefinition('annotated_test_custom_source'));
  }

  /**
   * Tests if FeedsCustomSource plugins defined with attributes can be found.
   */
  public function testFindAttributedCustomSourcePlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.custom_source')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_custom_source',
      'title' => new TranslatableMarkup('Attribute Test Custom Source'),
      'description' => new TranslatableMarkup('A test custom source plugin using attributes.'),
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestCustomSource::class,
      'plugin_type' => 'custom_source',
      'form' => [
        'configuration' => AttributeTestCustomSource::class,
      ],
    ];

    $actual = $definitions['attribute_test_custom_source'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.custom_source')->hasDefinition('attribute_test_custom_source'));
  }

  /**
   * Tests if FeedsTarget plugins defined with annotation can be found.
   */
  public function testFindAnnotatedTargetPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.target')->getDefinitions();

    $expected = [
      'id' => 'annotated_test_target',
      'field_types' => ['text'],
      'provider' => 'feeds_test_plugin',
      'class' => AnnotatedTestTarget::class,
      'plugin_type' => 'target',
      'form' => [
        'configuration' => AnnotatedTestTarget::class,
      ],
    ];

    $actual = $definitions['annotated_test_target'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.target')->hasDefinition('annotated_test_target'));
  }

  /**
   * Tests if FeedsTarget plugins defined with attributes can be found.
   */
  public function testFindAttributedTargetPlugins() {
    $definitions = $this->container->get('plugin.manager.feeds.target')->getDefinitions();

    $expected = [
      'id' => 'attribute_test_target',
      'title' => new TranslatableMarkup('Attribute Test Target'),
      'description' => new TranslatableMarkup('A test target plugin using attributes.'),
      'field_types' => ['text'],
      'provider' => 'feeds_test_plugin',
      'class' => AttributeTestTarget::class,
      'plugin_type' => 'target',
      'form' => [
        'configuration' => AttributeTestTarget::class,
      ],
    ];

    $actual = $definitions['attribute_test_target'];
    // Remove dependencies key if present (added in Drupal 11.3+).
    unset($actual['dependencies']);
    $this->assertEquals($expected, $actual);
    $this->assertTrue($this->container->get('plugin.manager.feeds.target')->hasDefinition('attribute_test_target'));
  }

}
