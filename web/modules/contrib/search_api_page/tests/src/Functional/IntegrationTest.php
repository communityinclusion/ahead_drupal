<?php

namespace Drupal\Tests\search_api_page\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\node\Entity\Node;
use Drupal\search_api_page\Entity\SearchApiPage;

/**
 * Provides web tests for Search API Pages.
 *
 * @group search_api_page
 */
class IntegrationTest extends FunctionalTestBase {

  /**
   * Tests Search API Pages.
   */
  public function testSearchApiPage() {
    $this->drupalLogin($this->adminUser);
    $assert_session = $this->assertSession();

    // Setup search api server and index.
    $this->setupSearchApi();

    $this->drupalGet('admin/config/search/search-api-pages');
    $assert_session->statusCodeEquals(200);

    $step1 = [
      'label' => 'Search',
      'id' => 'search',
      'index' => $this->index->id(),
    ];
    $this->drupalGet('admin/config/search/search-api-pages/add');
    $this->submitForm($step1, 'Next');

    // Test whether a leading slash leads to a form error.
    $step2 = [
      'path' => '/search',
    ];
    $this->submitForm($step2, 'Save');
    $assert_session->responseContains('The path should not contain leading or trailing slashes.');

    // Test whether a trailing slash leads to a form error.
    $step2 = [
      'path' => 'search/',
    ];
    $this->submitForm($step2, 'Save');
    $assert_session->responseContains('The path should not contain leading or trailing slashes.');

    // Test whether both a leading slash and a trailing slash leads to a form
    // error.
    $step2 = [
      'path' => '/search/',
    ];
    $this->submitForm($step2, 'Save');
    $assert_session->responseContains('The path should not contain leading or trailing slashes.');

    $step2 = [
      'path' => 'search',
    ];
    $this->submitForm($step2, 'Save');

    $assert_session->responseNotContains('The path should not contain leading or trailing slashes.');

    $this->drupalGet('search');
    $assert_session->responseContains('Enter the terms you wish to search for.');
    $assert_session->pageTextNotContains('Your search yielded no results.');
    $assert_session->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalLogin($this->unauthorizedUser);
    $this->drupalGet('search');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogout();
    $this->drupalLogin($this->anonymousUser);
    $this->drupalGet('search');
    $assert_session->statusCodeEquals(200);

    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('search/nothing-found');
    $assert_session->responseContains('Enter the terms you wish to search for.');
    $assert_session->pageTextContains('Your search yielded no results.');
    $this->drupalGet('search');
    $assert_session->pageTextNotContains('Your search yielded no results.');
    $this->drupalGet('admin/config/search/search-api-pages/search');

    $this->submitForm(['show_all_when_no_keys' => TRUE, 'show_search_form' => FALSE], 'Save');
    $this->drupalGet('search');
    $assert_session->pageTextNotContains('Your search yielded no results.');
    $assert_session->responseNotContains('Enter the terms you wish to search for.');
    $assert_session->pageTextContains('49 results found');

    $this->drupalGet('search/number10');
    $assert_session->pageTextContains('1 result found');
    $this->drupalGet('admin/config/search/search-api-pages/search');

    $this->submitForm(['show_search_form' => TRUE], 'Save');

    $this->drupalGet('search/number11');
    $assert_session->pageTextContains('1 result found');
    $assert_session->responseContains('name="keys" value="number11"');

    $this->drupalGet('search/number10');
    $assert_session->pageTextContains('1 result found');
    $assert_session->responseContains('name="keys" value="number10"');
  }

  /**
   * Tests cacheability metadata.
   */
  public function testCacheability() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);
    $this->setupSearchAPI();
    $this->setUpPage($this->index);

    $this->drupalGet('/search');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['keys' => 'Owls'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');
    $assert_session->pageTextNotContains('49 results found');
    $this->submitForm(['keys' => 'birds'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');
    $assert_session->pageTextNotContains('49 results found');
    $this->submitForm(['keys' => 'Strigiformes'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');
    $assert_session->pageTextNotContains('49 results found');

    $node = Node::load(3);
    $node->setTitle('More Owls #3');
    $node->save();
    $this->indexItems($this->index->id());

    $this->submitForm(['keys' => 'Owls'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('10 results found');
  }

  /**
   * Sets up an environment for testing + delegates to other methods for tests.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);
    $this->setupSearchAPI();
    $this->setUpPage($this->index);

    $this->drupalGet('/search');
    $this->assertSession()->statusCodeEquals(200);

    // The setup was done, we now have a search page set up
    // at /search, we can use that to do the rest of our testing.
    $this->checkMultipleWordSearch();
    $this->checkSpacesinSearch();
    $this->checkSlashSearch();
    $this->checkUndefinedLanguageItemsAreFound();
  }

  /**
   * Regression test for 2949069.
   */
  protected function checkMultipleWordSearch() {
    $assert_session = $this->assertSession();
    $this->drupalGet('/search');
    $assert_session->statusCodeEquals(200);

    $this->submitForm(['keys' => 'Owls'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');

    $this->submitForm(['keys' => 'birds of prey'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');

    $this->submitForm(['keys' => 'prey birds'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');
  }

  /**
   * Regression test for 2904203.
   */
  protected function checkSpacesInSearch() {
    $assert_session = $this->assertSession();
    $this->drupalGet('/search');
    $assert_session->statusCodeEquals(200);

    $this->submitForm(['keys' => 'Owls '], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');

    $this->submitForm(['keys' => ' Owls'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('9 results found');
  }

  /**
   * Regression test for #2721619.
   */
  protected function checkSlashSearch() {
    $this->drupalCreateNode([
      'title' => 'Another article',
      'type' => 'article',
      'body' => [['value' => 'foo/bar/qux fubar']],
    ]);
    $this->indexItems($this->index->id());

    $assert_session = $this->assertSession();
    $this->drupalGet('/search');
    $assert_session->statusCodeEquals(200);

    $this->submitForm(['keys' => 'foo/bar'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
  }

  /**
   * Tests that search handles backend exceptions gracefully.
   */
  public function testSearchBackendException() {
    $this->drupalLogin($this->adminUser);
    $this->setupSearchAPI();
    $this->setUpPage($this->index);

    // Verify the page works normally first.
    $this->drupalGet('/search');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm(['keys' => 'Owls'], 'Search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('9 results found');

    // Enable the exception flag to simulate a backend failure (e.g. Solr
    // becoming unavailable).
    \Drupal::state()->set('search_api_page_test.throw_exception', TRUE);

    // Searching with clean URLs should return 200, not 500. Use a different
    // search term than above to avoid the dynamic page cache.
    $this->drupalGet('/search/birds');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your search yielded no results.');

    // Test with non-clean URLs as well.
    $search_api_page = \Drupal::entityTypeManager()
      ->getStorage('search_api_page')
      ->load('search');
    $search_api_page->set('clean_url', FALSE)->save();
    \Drupal::service('router.builder')->rebuild();

    $this->drupalGet('/search', ['query' => ['keys' => 'birds']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Your search yielded no results.');
  }

  /**
   * Regression test for #3053095.
   */
  protected function checkUndefinedLanguageItemsAreFound() {
    $this->drupalCreateNode([
      'title' => 'Another article',
      'type' => 'article',
      'body' => [['value' => 'Undefined language']],
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);

    $this->indexItems($this->index->id());
    $assert_session = $this->assertSession();
    $this->drupalGet('/search');
    $assert_session->statusCodeEquals(200);

    $this->submitForm(['keys' => 'Undefined'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
  }

  /**
   * Tests that empty searched_fields searches all fulltext fields.
   */
  public function testEmptySearchedFieldsSearchesAll() {
    $this->drupalLogin($this->adminUser);
    $this->setupSearchApi();

    // Create a search page with no explicit searched fields.
    SearchApiPage::create([
      'label' => 'Search',
      'id' => 'search',
      'path' => 'search',
      'index' => $this->index->id(),
      'searched_fields' => [],
      'parse_mode' => 'terms',
    ])->save();

    $this->drupalGet('/search');
    $this->submitForm(['keys' => 'Owls'], 'Search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('9 results found');
  }

}
