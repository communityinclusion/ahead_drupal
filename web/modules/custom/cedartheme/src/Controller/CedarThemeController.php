<?php

namespace Drupal\cedartheme\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cedartheme\CedarThemeSalutation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the salutation message.
 */
class CedarThemeController extends ControllerBase {

  /**
   * @var \Drupal\cedartheme\CedarThemeSalutation
   */
  protected $salutation;

  /**
   * CedarThemeController constructor.
   *
   * @param \Drupal\cedartheme\CedarThemeSalutation $salutation
   */
  public function __construct(CedarThemeSalutation $salutation) {
    $this->salutation = $salutation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cedartheme.salutation')
    );
  }

  /**
   * Hello World.
   *
   * @return array
   */
  public function helloWorld() {
    return [
      '#lazy_builder' => ['cedartheme.lazy_builder:renderSalutation', []],
      '#create_placeholder' => TRUE,
    ];
  }

  /**
   * Handles the access checking.
   *
   * It's not actually used anywhere anymore
   * since we opted for the service-based approach so this method is no longer
   * referenced in the route definition.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function access(AccountInterface $account) {
    return in_array('editor', $account->getRoles()) ? AccessResult::forbidden() : AccessResult::allowed();
  }

  /**
   * Route callback for hiding the Salutation block.
   * Only works for Ajax calls.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function hideBlock(Request $request) {
    if (!$request->isXmlHttpRequest()) {
      throw new NotFoundHttpException();
    }

    $response = new AjaxResponse();
    $command = new RemoveCommand('.block-hello-world');
    $response->addCommand($command);
    return $response;
  }


}
