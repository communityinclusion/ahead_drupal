<?php

namespace Drupal\feeds_test_plugin\Feeds\Target;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsTarget;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Plugin\Type\Target\TargetBase;
use Drupal\feeds\TargetDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a test target that extends TargetBase directly with unique property.
 */
#[FeedsTarget(
  id: 'unique_test_target',
  title: new TranslatableMarkup('Unique Test Target'),
  description: new TranslatableMarkup('A test target plugin that extends TargetBase directly with unique property support.'),
)]
class UniqueTestTarget extends TargetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UniqueTestTarget object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function targets(array &$targets, FeedTypeInterface $feed_type, array $definition) {
    $target = TargetDefinition::create()
      ->setLabel(t('Unique test target'))
      ->setDescription(t('A test target with unique property support.'))
      ->addProperty('code', t('Code'), t('A unique code property.'))
      ->markPropertyUnique('code');
    $target->setPluginId($definition['id']);
    $targets['unique_test_target'] = $target;
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget(FeedInterface $feed, EntityInterface $entity, $target, array $values) {
    // For this test plugin, we store the code value in the title field
    // for simplicity. In a real implementation, this would store data
    // in a custom field or property.
    if (!empty($values[0]['code'])) {
      $entity->setTitle($values[0]['code']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isMutable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(FeedInterface $feed, EntityInterface $entity, $target) {
    // Check if the title is empty (where we store the code value).
    return $entity->get('title')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getUniqueValue(FeedInterface $feed, $target, $key, $value) {
    // Convert value to string.
    if (is_array($value)) {
      $value = reset($value);
    }
    $value = (string) $value;

    if (empty($value)) {
      return NULL;
    }

    $processor = $this->feedType->getProcessor();
    $entity_type = $processor->entityType();

    // Search for an entity with matching title (where we store the code).
    $query = $this->entityTypeManager->getStorage($entity_type)->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', $value)
      ->range(0, 1);

    // Restrict search to the same bundle if the entity type supports bundles.
    $bundle_key = $processor->bundleKey();
    if ($bundle_key) {
      $query->condition($bundle_key, $processor->bundle());
    }

    $result = $query->execute();
    if (!empty($result)) {
      return reset($result);
    }

    return NULL;
  }

}
