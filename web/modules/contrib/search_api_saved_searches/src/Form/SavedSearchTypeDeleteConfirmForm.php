<?php

namespace Drupal\search_api_saved_searches\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityDeleteFormTrait;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting saved search types.
 */
class SavedSearchTypeDeleteConfirmForm extends EntityConfirmFormBase {

  use EntityDeleteFormTrait;

  /**
   * The config manager.
   *
   * @var \Drupal\Core\Config\ConfigManager|null
   */
  protected $configManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $form = parent::create($container);

    $form->setConfigManager($container->get('config.manager'));

    return $form;
  }

  /**
   * Retrieves the config manager.
   *
   * @return \Drupal\Core\Config\ConfigManager
   *   The config manager.
   */
  public function getConfigManager(): ConfigManager {
    return $this->configManager ?: \Drupal::service('config.manager');
  }

  /**
   * Sets the config manager.
   *
   * @param \Drupal\Core\Config\ConfigManager $config_manager
   *   The new config manager.
   *
   * @return $this
   */
  public function setConfigManager(ConfigManager $config_manager): self {
    $this->configManager = $config_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    try {
      $num_searches = $this->entityTypeManager
        ->getStorage('search_api_saved_search')
        ->getQuery()
        ->condition('type', $this->entity->id())
        ->count()
        ->accessCheck(FALSE)
        ->execute();
    }
    catch (PluginException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      // This should make sure whoever sees this realizes something went wrong –
      // while also preventing them from deleting the type, since we cannot tell
      // whether that would be safe.
      $num_searches = -1;
    }
    if ($num_searches) {
      $caption = '<p>' . $this->formatPlural($num_searches, '%type is used by 1 saved search on your site. You cannot remove this saved search type until you have removed all of the %type saved searches.', '%type is used by @count saved searches on your site. You cannot remove this saved search type until you have removed all of the %type saved searches.', ['%type' => $this->entity->label()]) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }

    $form = parent::buildForm($form, $form_state);

    // Add information about the changes to dependent entities.
    // @see \Drupal\Core\Entity\EntityDeleteForm::buildForm()
    /** @var \Drupal\search_api_saved_searches\SavedSearchTypeInterface $entity */
    $entity = $this->getEntity();
    $this->addDependencyListsToForm($form, $entity->getConfigDependencyKey(), $this->getConfigNamesToDelete($entity), $this->getConfigManager(), $this->entityTypeManager);

    return $form;
  }

  /**
   * Returns config names to delete for the deletion confirmation form.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The entity being deleted.
   *
   * @return string[]
   *   A list of configuration names that will be deleted by this form.
   */
  protected function getConfigNamesToDelete(ConfigEntityInterface $entity): array {
    return [$entity->getConfigDependencyName()];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you really want to delete this saved search type?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * Returns the route to go to if the user cancels the action.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Thrown if the URL could not be created.
   */
  public function getCancelUrl(): Url {
    return $this->entity->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->entity->delete();
      $this->messenger()
        ->addStatus($this->t('The saved search type was successfully deleted.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
    catch (EntityStorageException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $error = $this->t('The saved search type could not be deleted due to an error: @message.', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($error);
    }
    catch (EntityMalformedException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $form_state->setRedirectUrl(new Url('<front>'));
    }
  }

}
