<?php

namespace Drupal\cedartheme;

/**
 * Lazy builder for the Hello World salutation.
 */
class CedarThemeLazyBuilder {

  /**
   * @var \Drupal\cedartheme\CedarThemeSalutation
   */
  protected $salutation;

  /**
   * CedarThemeLazyBuilder constructor.
   *
   * @param \Drupal\cedartheme\CedarThemeSalutation $salutation
   */
  public function __construct(CedarThemeSalutation $salutation) {
    $this->salutation = $salutation;
  }

  /**
   * Renders the Hello World salutation message.
   */
  public function renderSalutation() {
    return $this->salutation->getSalutationComponent();
  }
}
