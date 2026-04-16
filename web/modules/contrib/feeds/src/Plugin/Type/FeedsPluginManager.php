<?php

namespace Drupal\feeds\Plugin\Type;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AttributeDiscoveryWithAnnotations;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\feeds\Annotation\FeedsFetcher as FeedsFetcherAnnotation;
use Drupal\feeds\Annotation\FeedsParser as FeedsParserAnnotation;
use Drupal\feeds\Annotation\FeedsProcessor as FeedsProcessorAnnotation;
use Drupal\feeds\Annotation\FeedsSource as FeedsSourceAnnotation;
use Drupal\feeds\Annotation\FeedsCustomSource as FeedsCustomSourceAnnotation;
use Drupal\feeds\Annotation\FeedsTarget as FeedsTargetAnnotation;
use Drupal\feeds\Attribute\FeedsFetcher as FeedsFetcherAttribute;
use Drupal\feeds\Attribute\FeedsParser as FeedsParserAttribute;
use Drupal\feeds\Attribute\FeedsProcessor as FeedsProcessorAttribute;
use Drupal\feeds\Attribute\FeedsSource as FeedsSourceAttribute;
use Drupal\feeds\Attribute\FeedsCustomSource as FeedsCustomSourceAttribute;
use Drupal\feeds\Attribute\FeedsTarget as FeedsTargetAttribute;
use Drupal\feeds\Plugin\Discovery\OverridableDerivativeDiscoveryDecorator;

/**
 * Manages Feeds plugins.
 */
class FeedsPluginManager extends DefaultPluginManager {

  /**
   * The plugin being managed.
   *
   * @var string
   */
  protected $pluginType;

  /**
   * Constructs a new \Drupal\feeds\Plugin\Type\FeedsPluginManager object.
   *
   * @param string $type
   *   The plugin type. Either fetcher, parser, or processor, handler, source,
   *   target, or other.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->pluginType = $type;
    $this->subdir = 'Feeds/' . ucfirst($type);
    $this->namespaces = $namespaces;
    if ($type == 'custom_source') {
      $this->subdir = 'Feeds/CustomSource';
    }
    $this->moduleHandler = $module_handler;
    $this->pluginDefinitionAttributeName = $this->getAttributeClass($type);
    $this->pluginDefinitionAnnotationName = $this->getAnnotationClass($type);
    $this->discovery = new AttributeDiscoveryWithAnnotations($this->subdir, $this->namespaces, $this->pluginDefinitionAttributeName, $this->pluginDefinitionAnnotationName, $this->additionalAnnotationNamespaces);
    $this->discovery = new OverridableDerivativeDiscoveryDecorator($this->discovery);
    $this->alterInfo("feeds_{$type}_plugins");
    $this->setCacheBackend($cache_backend, "feeds_{$type}_plugins");
  }

  /**
   * Returns the attribute class to use for the given plugin type.
   *
   * @param string $type
   *   The Feeds plugin type.
   *
   * @return string
   *   The attribute class for the given type.
   */
  protected function getAttributeClass(string $type): string {
    return match ($type) {
      'fetcher' => FeedsFetcherAttribute::class,
      'parser' => FeedsParserAttribute::class,
      'processor' => FeedsProcessorAttribute::class,
      'source' => FeedsSourceAttribute::class,
      'custom_source' => FeedsCustomSourceAttribute::class,
      'target' => FeedsTargetAttribute::class,
    };
  }

  /**
   * Returns the annotation class to use for the given plugin type.
   *
   * @param string $type
   *   The Feeds plugin type.
   *
   * @return string
   *   The annotation class for the given type.
   */
  protected function getAnnotationClass(string $type): string {
    return match ($type) {
      'fetcher' => FeedsFetcherAnnotation::class,
      'parser' => FeedsParserAnnotation::class,
      'processor' => FeedsProcessorAnnotation::class,
      'source' => FeedsSourceAnnotation::class,
      'custom_source' => FeedsCustomSourceAnnotation::class,
      'target' => FeedsTargetAnnotation::class,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    // Add plugin_type key so that we can determine the plugin type later.
    $definition['plugin_type'] = $this->pluginType;

    // If no default form is defined and this plugin implements
    // \Drupal\Core\Plugin\PluginFormInterface, use that for the default form.
    if (!isset($definition['form']['configuration']) && isset($definition['class']) && is_subclass_of($definition['class'], PluginFormInterface::class)) {
      $definition['form']['configuration'] = $definition['class'];
    }
  }

}
