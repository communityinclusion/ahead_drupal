<?php

namespace Drupal\aheadtasks\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class AheadTasksController.
 */
class AheadTasksController extends ControllerBase {

  /**
   * Ahead.
   *
   * @return string
   *   Return Hello string.
   */
  public function Ahead() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: Ahead')
    ];
  }

}
