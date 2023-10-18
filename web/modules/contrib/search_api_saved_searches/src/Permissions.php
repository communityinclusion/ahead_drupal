<?php

namespace Drupal\search_api_saved_searches;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions of the search_api_autocomplete module.
 */
class Permissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The saved search type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $object = new static();

    $storage = $container->get('entity_type.manager')
      ->getStorage('search_api_saved_search_type');
    $object->setStorage($storage);
    $object->setStringTranslation($container->get('string_translation'));

    return $object;
  }

  /**
   * Retrieves the saved search type storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The saved search type storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown if the storage handler could not be retrieved.
   */
  public function getStorage(): EntityStorageInterface {
    return $this->storage ?: \Drupal::entityTypeManager()
      ->getStorage('search_api_saved_search_type');
  }

  /**
   * Sets the saved search type storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The new saved search type storage.
   *
   * @return $this
   */
  public function setStorage(EntityStorageInterface $storage): self {
    $this->storage = $storage;
    return $this;
  }

  /**
   * Returns a list of permissions, one per configured saved search type.
   *
   * @return array[]
   *   A list of permission definitions, keyed by permission machine name.
   */
  public function bySavedSearchType(): array {
    $perms = [];

    try {
      foreach ($this->getStorage()->loadMultiple() as $id => $type) {
        $args = ['%type' => $type->label()];
        $perms['use ' . $id . ' search_api_saved_searches'] = [
          'title' => $this->t('Use saved searches of type %type', $args),
        ];
      }
    }
    catch (PluginException $e) {
      watchdog_exception('search_api_saved_searches', $e);
    }

    return $perms;
  }

}
