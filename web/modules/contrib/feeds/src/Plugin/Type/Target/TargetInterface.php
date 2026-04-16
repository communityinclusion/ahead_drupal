<?php

namespace Drupal\feeds\Plugin\Type\Target;

use Drupal\Core\Entity\EntityInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Plugin\DependentWithRemovalPluginInterface;

/**
 * Interface for Feed targets.
 */
interface TargetInterface extends DependentWithRemovalPluginInterface {

  /**
   * Returns the targets defined by this plugin.
   *
   * @param \Drupal\feeds\TargetDefinitionInterface[] $targets
   *   An array of targets.
   * @param \Drupal\feeds\FeedTypeInterface $feed_type
   *   The feed type object.
   * @param array $definition
   *   The plugin implementation definition.
   */
  public static function targets(array &$targets, FeedTypeInterface $feed_type, array $definition);

  /**
   * Sets the values on an object.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target object.
   * @param string $target
   *   The name of the target to set.
   * @param array $values
   *   A list of values to set on the target.
   */
  public function setTarget(FeedInterface $feed, EntityInterface $entity, $target, array $values);

  /**
   * Clears the target on an object.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target object.
   * @param string $target
   *   The name of the target to unset.
   */
  public function clearTarget(FeedInterface $feed, EntityInterface $entity, string $target);

  /**
   * Returns the target's definition.
   *
   * @return \Drupal\feeds\TargetDefinitionInterface
   *   The definition for this target.
   */
  public function getTargetDefinition();

  /**
   * Returns if the target is mutable.
   *
   * @return bool
   *   True if the target is mutable. False otherwise.
   */
  public function isMutable();

  /**
   * Returns if the value for the target is empty.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The target object.
   * @param string $target
   *   The name of the target to check.
   *
   * @return bool
   *   True if the value on the entity is empty. False otherwise.
   */
  public function isEmpty(FeedInterface $feed, EntityInterface $entity, $target);

  /**
   * Looks for an existing entity and returns an entity ID if found.
   *
   * This method is used by the entity processor to find existing entities
   * based on unique target values. If a target plugin does not support
   * unique value lookup, it should return NULL.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed that is being processed.
   * @param string $target
   *   The ID of the target plugin.
   * @param string $key
   *   The property of the target to search on.
   * @param mixed $value
   *   The value to look for.
   *
   * @return string|int|null
   *   An entity ID, if found. Null otherwise.
   */
  public function getUniqueValue(FeedInterface $feed, $target, $key, $value);

}
