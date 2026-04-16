<?php

namespace Drupal\feeds_test_plugin\Feeds\Target;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Attribute\FeedsTarget;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Plugin\Type\Target\TargetBase;
use Drupal\feeds\TargetDefinition;

/**
 * Defines a test target that extends TargetBase without getUniqueValue().
 */
#[FeedsTarget(
  id: 'non_unique_test_target',
  title: new TranslatableMarkup('Non-Unique Test Target'),
  description: new TranslatableMarkup('A test target plugin that extends TargetBase without getUniqueValue() implementation.'),
)]
class NonUniqueTestTarget extends TargetBase {

  /**
   * {@inheritdoc}
   */
  public static function targets(array &$targets, FeedTypeInterface $feed_type, array $definition) {
    $target = TargetDefinition::create()
      ->setLabel(t('Non-unique test target'))
      ->setDescription(t('A test target without getUniqueValue() implementation.'))
      ->addProperty('code', t('Code'), t('A code property.'))
      ->markPropertyUnique('code');
    $target->setPluginId($definition['id']);
    $targets['non_unique_test_target'] = $target;
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

}
