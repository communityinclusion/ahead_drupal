<?php

namespace Drupal\Tests\feeds\Unit\Component;

use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use Drupal\feeds\Component\XmlParserTrait;

/**
 * @coversDefaultClass \Drupal\feeds\Component\XmlParserTrait
 * @group feeds
 */
class XmlParserTraitTest extends FeedsUnitTestCase {

  /**
   * Basic XML parsing test.
   */
  public function test() {
    $trait = $this->createMock(XmlParserTraitMock::class);

    $doc = $this->callProtectedMethod($trait, 'getDomDocument', [' <thing></thing> ']);
    $this->assertSame('DOMDocument', get_class($doc));

    $errors = $this->callProtectedMethod($trait, 'getXmlErrors');
    $this->assertSame([], $errors);
  }

  /**
   * Tests parsing a document with invalid XML.
   */
  public function testErrors() {
    $trait = $this->createMock(XmlParserTraitMock::class);

    $doc = $this->callProtectedMethod($trait, 'getDomDocument', ['asdfasdf']);
    $this->assertSame('DOMDocument', get_class($doc));

    $errors = $this->callProtectedMethod($trait, 'getXmlErrors');
    $this->assertSame("Start tag expected, '<' not found", $errors[3][0]['message']);
  }

  /**
   * Strip some namespaces out of XML.
   *
   * @dataProvider namespaceProvider
   */
  public function testRemoveDefaultNamespaces($in, $out) {
    $trait = $this->createMock(XmlParserTraitMock::class);

    $result = $this->callProtectedMethod($trait, 'removeDefaultNamespaces', [$in]);
    $this->assertSame($out, $result);
  }

  /**
   * Data provider for testRemoveDefaultNamespaces().
   *
   * Checks that the input and output are equal.
   */
  public static function namespaceProvider() {
    return [
      [
        '<feed xmlns="http://www.w3.org/2005/Atom">bleep blorp</feed>',
        '<feed>bleep blorp</feed>',
      ],
      [
        '<подача xmlns="http://www.w3.org/2005/Atom">bleep blorp</подача>',
        '<подача>bleep blorp</подача>',
      ],
      [
        '<по.дача xmlns="http://www.w3.org/2005/Atom">bleep blorp</по.дача>',
        '<по.дача>bleep blorp</по.дача>',
      ],
      [
        '<element other attrs xmlns="http://www.w3.org/2005/Atom">bleep blorp</element>',
        '<element other attrs>bleep blorp</element>',
      ],
      [
        '<cat xmlns="http://www.w3.org/2005/Atom" other attrs>bleep blorp</cat>',
        '<cat other attrs>bleep blorp</cat>',
      ],
      [
        '<飼料 thing="stuff" xmlns="http://www.w3.org/2005/Atom">bleep blorp</飼料>',
        '<飼料 thing="stuff">bleep blorp</飼料>',
      ],
      [
        '<飼-料 thing="stuff" xmlns="http://www.w3.org/2005/Atom">bleep blorp</飼-料>',
        '<飼-料 thing="stuff">bleep blorp</飼-料>',
      ],
      [
        '<self xmlns="http://www.w3.org/2005/Atom" />',
        '<self />',
      ],
      [
        '<self attr xmlns="http://www.w3.org/2005/Atom"/>',
        '<self attr/>',
      ],
      [
        '<a xmlns="http://www.w3.org/2005/Atom"/>',
        '<a/>',
      ],
      [
        '<a xmlns="http://www.w3.org/2005/Atom"></a>',
        '<a></a>',
      ],
      [
        '<a href="http://google.com" xmlns="http://www.w3.org/2005/Atom"></a>',
        '<a href="http://google.com"></a>',
      ],

      // Test invalid XML element names.
      [
        '<1name href="http://google.com" xmlns="http://www.w3.org/2005/Atom"></1name>',
        '<1name href="http://google.com" xmlns="http://www.w3.org/2005/Atom"></1name>',
      ],

      // Test other namespaces.
      [
        '<name href="http://google.com" xmlns:h="http://www.w3.org/2005/Atom"></name>',
        '<name href="http://google.com" xmlns:h="http://www.w3.org/2005/Atom"></name>',
      ],

      // Test multiple default namespaces.
      [
        '<name xmlns="http://www.w3.org/2005/Atom"></name><name xmlns="http://www.w3.org/2005/Atom"></name>',
        '<name></name><name></name>',
      ],

      // Test with no namespaces.
      [
        '<root><item id="1">Content</item></root>',
        '<root><item id="1">Content</item></root>',
      ],
    ];
  }

  /**
   * Tests PCRE backtracking in removeDefaultNamespaces().
   *
   * When many attributes with quotes appear before xmlns, the regex
   * `.*?` must try many positions to find the matching quote, causing
   * exponential backtracking. This test sets a low PCRE backtrack
   * limit to trigger the issue.
   */
  public function testRemoveDefaultNamespacesCatastrophicBacktracking(): void {
    // Store original limit and set a low value to force the issue.
    $original_limit = ini_set('pcre.backtrack_limit', 50);

    try {
      // This should trigger the PCRE backtrack limit.
      $trait = $this->createMock(XmlParserTraitMock::class);
      $xml = file_get_contents(__DIR__ . '/../../../fixtures/feeds-test-backtrace-limit.xml');
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage('PCRE error while processing XML namespaces: Backtrack limit exhausted (' . PREG_BACKTRACK_LIMIT_ERROR . ')');
      $this->expectExceptionCode(PREG_BACKTRACK_LIMIT_ERROR);
      $result = $this->callProtectedMethod($trait, 'removeDefaultNamespaces', [$xml]);

      // Verify that we have a result that can be loaded.
      $this->callProtectedMethod($trait, 'getDomDocument', [$result]);
    }
    finally {
      // Restore original backtrack limit.
      ini_set('pcre.backtrack_limit', $original_limit);
    }
  }

}

/**
 * Mock for XmlParserTrait, so trait methods can be tested.
 */
class XmlParserTraitMock {

  use XmlParserTrait;

}
