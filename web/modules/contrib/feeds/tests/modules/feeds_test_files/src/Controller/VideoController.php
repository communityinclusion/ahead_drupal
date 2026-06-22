<?php

namespace Drupal\feeds_test_files\Controller;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class for routes related to video media.
 */
class VideoController {

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a VideoController object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * Serves a video file from the assets folder.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A http response.
   */
  public function serveVideo(): Response {
    $feeds_path = $this->moduleHandler->getModule('feeds')->getPath();
    $video_path = DRUPAL_ROOT . '/' . $feeds_path . '/tests/resources/assets/apenheul.mp4';

    if (!file_exists($video_path)) {
      return new Response('Video file not found', 404);
    }

    $response = new BinaryFileResponse($video_path);
    $response->headers->set('Content-Type', 'video/mp4');
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'apenheul.mp4');

    return $response;
  }

}
