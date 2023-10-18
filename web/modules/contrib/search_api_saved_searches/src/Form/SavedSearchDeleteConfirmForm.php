<?php

namespace Drupal\search_api_saved_searches\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a saved search.
 */
class SavedSearchDeleteConfirmForm extends ContentEntityConfirmFormBase {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\search_api_saved_searches\SavedSearchInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you really want to delete this saved search?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    if (!empty($this->entity->getOwnerId())) {
      $redirect = '/user/' . $this->entity->getOwnerId() . '/saved-searches';
      return Url::fromUserInput($redirect);
    }
    return Url::fromUri('internal:/');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->entity->delete();
      $this->messenger()
        ->addStatus($this->t('The saved search was successfully deleted.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
    catch (EntityStorageException $e) {
      watchdog_exception('search_api_saved_searches', $e);
      $error = $this->t('The saved search could not be deleted due to an internal error.');
      $this->messenger()->addError($error);
    }
  }

}
