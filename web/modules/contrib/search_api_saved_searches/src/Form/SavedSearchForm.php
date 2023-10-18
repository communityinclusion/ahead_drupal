<?php

namespace Drupal\search_api_saved_searches\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a standard form for editing saved searches.
 */
class SavedSearchForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\search_api_saved_searches\SavedSearchInterface $search */
    $search = $this->getEntity();

    $args['%search_label'] = $search->label();
    $form['#title'] = $this->t('Edit saved search %search_label', $args);

    return $form;
  }

}
