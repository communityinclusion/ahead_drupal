<?php

namespace Drupal\Tests\feeds_para_mapper\Functional;



/**
 * Test Importing.
 * @group Feeds Paragraphs
 */
class ImportingTest extends FeedsParaMapperTestBase {
  protected $defaultTheme = 'stark';
  protected function setUp()
  {
    parent::setUp();
  }

  public function testThings(){
    $this->drupalGet('http://localhost/admin/structure/feeds');
    dump('my data');
  }
}
