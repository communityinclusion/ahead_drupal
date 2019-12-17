<?php

namespace Drupal\Tests\cedartheme\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Basic testing of the main Hello World page.
 *
 * @group cedartheme
 */
class CedarThemePageTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cedartheme', 'user'];

  /**
   * Tests the main Hello World page.
   */
  public function testPage() {
    $expected = $this->assertDefaultSalutation();
    $config = $this->config('cedartheme.custom_salutation');
    $config->set('salutation', 'Testing salutation');
    $config->save();

    $this->drupalGet('/hello');
    $this->assertSession()->pageTextNotContains($expected);
    $expected = 'Testing salutation';
    $this->assertSession()->pageTextContains($expected);
  }

  /**
   * Helper function to assert that the default salutation is present on the page.
   *
   * Returns the message so we can reuse it in multiple places.
   */
  private function assertDefaultSalutation() {
    $this->drupalGet('/hello');
    $this->assertSession()->elementTextContains('css', 'h1', 'Our first route');
    $time = new \DateTime();
    $expected = '';
    if ((int) $time->format('G') >= 06 && (int) $time->format('G') < 12) {
      $expected = 'Good morning';
    }

    if ((int) $time->format('G') >= 12 && (int) $time->format('G') < 18) {
      $expected = 'Good afternoon';
    }

    if ((int) $time->format('G') >= 18) {
      $expected = 'Good evening';
    }
    $expected .= ' world';
    $this->assertSession()->pageTextContains($expected);
    return $expected;
  }

  /**
   * Tests that the configuration form for overriding the message works.
   */
  public function testForm() {
    $expected = $this->assertDefaultSalutation();
    $this->drupalGet('/admin/config/salutation-configuration');
    $this->assertSession()->statusCodeEquals(403);
    $account = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/config/salutation-configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Salutation configuration');
    $this->assertSession()->elementExists('css', '#edit-salutation');

    $edit = [
      'salutation' => 'My custom salutation',
    ];

    $this->drupalPostForm(NULL, $edit, 'op');
    $this->assertSession()->pageTextContains('The configuration options have been saved');
    $this->drupalGet('/hello');
    $this->assertSession()->pageTextNotContains($expected);
    $this->assertSession()->pageTextContains('My custom salutation');
  }

}
