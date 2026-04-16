<?php

namespace Drupal\feeds\Plugin\Type\Processor;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\CleanableInterface;
use Drupal\feeds\Plugin\Type\ClearableInterface;
use Drupal\feeds\Plugin\Type\LockableInterface;

/**
 * Interface for Feeds entity processor plugins.
 */
interface EntityProcessorInterface extends ProcessorInterface, ClearableInterface, CleanableInterface, LockableInterface {

  /**
   * Returns a translation of the given entity.
   *
   * If a translation of the requested language does not exist yet on the
   * entity, one is created.
   *
   * @param \Drupal\feeds\FeedInterface $feed
   *   The feed that controls the import.
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   A translatable entity.
   * @param string $langcode
   *   The language in which to get the translation.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The translated entity.
   */
  public function getEntityTranslation(FeedInterface $feed, TranslatableInterface $entity, $langcode);

  /**
   * Returns the current language for entities.
   *
   * @return string
   *   The current language code.
   */
  public function entityLanguage();

  /**
   * Returns the entity type id of the entities.
   *
   * @return string
   *   The entity type id.
   */
  public function entityType();

  /**
   * Returns the bundle id of the entities.
   *
   * @return string|null
   *   The entity bundle id or NULL if the entity type does not have bundles.
   */
  public function bundle();

  /**
   * Returns the bundle key of the entity type.
   *
   * @return string|null
   *   The entity bundle key or NULL if the entity type does not have bundles.
   */
  public function bundleKey();

}
