<?php

namespace Drupal\Tests\tamper\Unit\Plugin\Tamper;

use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\Plugin\Tamper\UrlEncode;

/**
 * Tests the url encode plugin.
 *
 * @coversDefaultClass \Drupal\tamper\Plugin\Tamper\UrlEncode
 * @group tamper
 */
class UrlEncodeTest extends TamperPluginTestBase {

  /**
   * {@inheritdoc}
   */
  protected function instantiatePlugin() {
    return new UrlEncode([], 'url_encode', [], $this->getMockSourceDefinition());
  }

  /**
   * Test url_encode symbols, string with spaces and special chars using legacy method.
   */
  public function testUrlEncodeString() {
    $config = [
      UrlEncode::SETTING_METHOD => 'urlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $this->assertEquals('%24+%26+%3C+%3E+%3F+%3B+%23+%3A+%3D+%2C+%22+%27+%7E+%2B+%25', $plugin->tamper('$ & < > ? ; # : = , " \' ~ + %'));
    $this->assertEquals('String+with+spaces', $plugin->tamper('String with spaces'));
    $this->assertEquals('special+chars%3A+%26%25%2A', $plugin->tamper('special chars: &%*'));
  }

  /**
   * Test url_encode of array input using legacy method.
   */
  public function testUrlEncodeArray() {
    $this->setExpectedException(TamperException::class, 'Input should be a string.');
    $config = [
      UrlEncode::SETTING_METHOD => 'urlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $plugin->tamper(['fOo', 'BAR']);
  }

  /**
   * Test url_encode of numeric input using legacy method.
   */
  public function testUrlEncodeNumeric() {
    $this->setExpectedException(TamperException::class, 'Input should be a string.');
    $config = [
      UrlEncode::SETTING_METHOD => 'urlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $plugin->tamper(14567);
  }

  /**
   * Test url_encode symbols, string with spaces and special chars using raw method.
   */
  public function testRawUrlEncodeString() {
    $config = [
      UrlEncode::SETTING_METHOD => 'rawurlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $this->assertEquals('%24%20%26%20%3C%20%3E%20%3F%20%3B%20%23%20%3A%20%3D%20%2C%20%22%20%27%20~%20%2B%20%25', $plugin->tamper('$ & < > ? ; # : = , " \' ~ + %'));
    $this->assertEquals('String%20with%20spaces', $plugin->tamper('String with spaces'));
    $this->assertEquals('special%20chars%3A%20%26%25%2A', $plugin->tamper('special chars: &%*'));
  }

  /**
   * Test url_encode array using raw method.
   */
  public function testRawUrlEncodeArray() {
    $this->setExpectedException(TamperException::class, 'Input should be a string.');
    $config = [
      UrlEncode::SETTING_METHOD => 'rawurlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $plugin->tamper(['fOo', 'BAR']);
  }

  /**
   * Test url_encode number using raw method.
   */
  public function testRawUrlEncodeNumeric() {
    $this->setExpectedException(TamperException::class, 'Input should be a string.');
    $config = [
      UrlEncode::SETTING_METHOD => 'rawurlencode',
    ];
    $plugin = new UrlEncode($config, 'url_encode', [], $this->getMockSourceDefinition());
    $plugin->tamper(14567);
  }

}
