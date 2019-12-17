<?php

namespace Drupal\cedartheme;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Prepares the salutation to the world.
 */
class CedarThemeSalutation {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * CedarThemeSalutation constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   */
  public function __construct(ConfigFactoryInterface $config_factory, EventDispatcherInterface $eventDispatcher, KillSwitch $killSwitch) {
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $eventDispatcher;
    $this->killSwitch = $killSwitch;
  }

  /**
   * Returns the salutation.
   */
  public function getSalutation() {
    $this->killSwitch->trigger();
    $config = $this->configFactory->get('cedartheme.custom_salutation');
    $salutation = $config->get('salutation');
    if ($salutation != "") {
      $event = new SalutationEvent();
      $event->setValue($salutation);
      $event = $this->eventDispatcher->dispatch(SalutationEvent::EVENT, $event);
      return $event->getValue();
    }

    $time = new \DateTime();

    if ((int) $time->format('G') >= 00 && (int) $time->format('G') < 12) {
      return $this->t('Good morning world');
    }

    if ((int) $time->format('G') >= 12 && (int) $time->format('G') < 18) {
      return $this->t('Good afternoon world');
    }

    if ((int) $time->format('G') >= 18) {
      return $this->t('Good evening world');
    }
  }

  /**
   * Returns the Salutation render array.
   */
  public function getSalutationComponent() {
    $this->killSwitch->trigger();
    $render = [
      '#theme' => 'cedartheme_salutation',
      '#salutation' => [
        '#contextual_links' => [
          'cedartheme' => [
            'route_parameters' => []
          ],
        ]
      ],
      '#cache' => [
        'max-age' => 0
      ]
    ];

    $config = $this->configFactory->get('cedartheme.custom_salutation');
    $salutation = $config->get('salutation');

    if ($salutation != "") {
      $render['#salutation']['#markup'] = $salutation;
      $render['#overridden'] = TRUE;
      return $render;
    }

    $time = new \DateTime();
    $render['#target'] = $this->t('world');
    $render['#attached'] = [
      'library' => [
        'cedartheme/cedartheme_clock'
      ]
    ];

    if ((int) $time->format('G') >= 06 && (int) $time->format('G') < 12) {
      $render['#salutation']['#markup'] = $this->t('Good morning');
      return $render;
    }

    if ((int) $time->format('G') >= 12 && (int) $time->format('G') < 18) {
      $render['#salutation']['#markup'] = $this->t('Good afternoon');
      $render['#attached']['drupalSettings']['cedartheme']['cedartheme_clock']['afternoon'] = TRUE;
      return $render;
    }

    if ((int) $time->format('G') >= 18) {
      $render['#salutation']['#markup'] = $this->t('Good evening');
      return $render;
    }

    return $render;
  }

}
