<?php

namespace Drupal\Tests\cedartheme\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Testing the simple Javascript timer on the Hello World page.
 *
 * @group cedartheme
 */
class TimeTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cedartheme'];

  /**
   * Tests the time component.
   */
  public function testTime() {
    $this->drupalGet('/hello');
    $this->assertSession()->pageTextContains('The time is');

    $config = $this->config('cedartheme.custom_salutation');
    $config->set('salutation', 'Testing salutation');
    $config->save();

    $this->drupalGet('/hello');
    $this->assertSession()->pageTextNotContains('The time is');
  }

}
