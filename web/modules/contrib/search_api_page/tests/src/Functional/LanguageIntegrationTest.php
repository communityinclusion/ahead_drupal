<?php

namespace Drupal\Tests\search_api_page\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\search_api_page\Entity\SearchApiPage;

/**
 * Provides web tests for Search API Pages with language integration.
 *
 * @group search_api_page
 */
class LanguageIntegrationTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->adminUser);
    $assert_session = $this->assertSession();

    ConfigurableLanguage::create([
      'id' => 'nl',
      'label' => 'Dutch',
    ])->save();
    ConfigurableLanguage::create([
      'id' => 'es',
      'label' => 'Spanish',
    ])->save();

    $bird_node = $this->drupalCreateNode([
      'title' => 'bird: Hawk',
      'language' => 'en',
      'type' => 'article',
      'body' => [['value' => 'Body translated']],
    ]);
    $bird_node->addTranslation('nl', ['title' => 'bird: Havik'])
      ->addTranslation('es', ['title' => 'bird: Halcon'])
      ->save();

    // Setup search api server and index.
    $this->setupSearchAPI();

    $this->drupalGet('admin/config/search/search-api-pages');
    $assert_session->statusCodeEquals(200);

    $step1 = [
      'label' => 'Search',
      'id' => 'search',
      'index' => $this->index->id(),
    ];
    $this->drupalGet('admin/config/search/search-api-pages/add');
    $this->submitForm($step1, 'Next');

    $step2 = [
      'path' => 'search',
    ];
    $this->submitForm($step2, 'Save');
  }

  /**
   * Tests Search API Pages language integration.
   */
  public function testSearchApiPage() {
    $assert_session = $this->assertSession();

    $this->drupalGet('/search');
    $this->submitForm(['keys' => 'bird'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
    $assert_session->pageTextContains('Hawk');
    $assert_session->pageTextNotContains('Your search yielded no results.');

    $this->drupalGet('/nl/search');
    $this->submitForm(['keys' => 'bird'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
    $assert_session->pageTextContains('Havik');
    $assert_session->pageTextNotContains('Your search yielded no results.');
  }

  /**
   * Translated paths should work after route rebuild in a non-default language.
   */
  public function testRouteRebuildInNonDefaultLanguage() {
    $assert_session = $this->assertSession();

    // Add a Dutch path translation for the search page.
    $this->container->get('language_manager')
      ->getLanguageConfigOverride('nl', 'search_api_page.search_api_page.search')
      ->set('path', 'zoeken')
      ->save();

    // Rebuild routes while config override language is set to Dutch,
    // simulating a cache clear triggered from a Dutch-language page.
    $language_manager = $this->container->get('language_manager');
    $dutch = $language_manager->getLanguage('nl');
    $language_manager->setConfigOverrideLanguage($dutch);
    $this->container->get('router.builder')->rebuild();
    $language_manager->setConfigOverrideLanguage($language_manager->getDefaultLanguage());

    // English path should still work.
    $this->drupalGet('/search');
    $this->submitForm(['keys' => 'bird'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
    $assert_session->pageTextContains('Hawk');

    // Dutch translated path should work.
    $this->drupalGet('/nl/zoeken');
    $this->submitForm(['keys' => 'bird'], 'Search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('1 result found');
    $assert_session->pageTextContains('Havik');
  }

  /**
   * Tests that the block form shows keywords on non-default language pages.
   */
  public function testBlockFormKeywordsOnNonDefaultLanguage() {
    $assert_session = $this->assertSession();

    // Create a search page with clean URLs disabled and no page form,
    // so only the block form is rendered.
    SearchApiPage::create([
      'label' => 'Search NoClean',
      'id' => 'search_noclean',
      'index' => $this->index->id(),
      'path' => 'search-noclean',
      'clean_url' => FALSE,
      'show_search_form' => FALSE,
      'searched_fields' => $this->index->getFulltextFields(),
      'parse_mode' => 'terms',
    ])->save();

    // Place the search block.
    $this->drupalPlaceBlock('search_api_page_form_block', [
      'search_api_page' => 'search_noclean',
    ]);

    // English: block should show the keywords.
    $this->drupalGet('/search-noclean', ['query' => ['keys' => 'bird']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldValueEquals('edit-keys', 'bird');

    // Dutch: block should also show the keywords.
    $this->drupalGet('/nl/search-noclean', ['query' => ['keys' => 'bird']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldValueEquals('edit-keys', 'bird');
  }

  /**
   * Tests that the page title uses the content language translation.
   *
   * When interface language and content language are configured independently,
   * the page title should follow the content language (from the URL), not the
   * interface language (from user preference or site default).
   */
  public function testPageTitleUsesContentLanguage() {
    $assert_session = $this->assertSession();

    // Configure content language to use URL detection independently from
    // interface language. Interface language is fixed to site default
    // (English).
    $this->config('language.types')
      ->set('configurable', ['language_interface', 'language_content'])
      ->save();
    \Drupal::service('language_negotiator')
      ->saveConfiguration('language_content', ['language-url' => -8]);
    \Drupal::service('language_negotiator')
      ->saveConfiguration('language_interface', ['language-selected' => 12]);
    $this->config('language.negotiation')
      ->set('selected_langcode', 'site_default')
      ->save();
    $this->rebuildContainer();

    // Add a Dutch label translation for the search page.
    $this->container->get('language_manager')
      ->getLanguageConfigOverride('nl', 'search_api_page.search_api_page.search')
      ->set('label', 'Zoeken')
      ->save();

    // Visit Dutch search page. Interface language is English (fixed), but
    // content language is Dutch (from URL). The page title should follow the
    // content language.
    $this->drupalGet('/nl/search');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Zoeken');
  }

  /**
   * Tests the url alias translation.
   *
   * @see https://www.drupal.org/node/2893374
   */
  public function testUrlAliasTranslation() {
    $page = SearchApiPage::create([
      'label' => 'Owl Display',
      'id' => 'owl_display',
      'index' => $this->index->id(),
      'path' => 'bird_owl',
      'show_all_when_no_keys' => TRUE,
    ]);
    $page->save();

    \Drupal::service('module_installer')->install(['locale']);
    $block = $this->drupalPlaceBlock('language_block:' . LanguageInterface::TYPE_INTERFACE, [
      'id' => 'test_language_block',
    ]);

    $this->drupalGet('bird_owl');
    $this->assertSession()->pageTextContains($block->label());
    $this->assertSession()->pageTextContains('50 results found');
    $this->assertSession()->statusCodeEquals(200);

    $this->clickLink('Spanish');
    $this->assertSession()->pageTextContains($block->label());
    $this->assertTrue((bool) strpos($this->getUrl(), '/es/'), 'Found the language code in the url');
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->statusCodeEquals(200);

    $this->clickLink('Dutch');
    $this->assertSession()->pageTextContains($block->label());
    $this->assertTrue((bool) strpos($this->getUrl(), '/nl/'), 'Found the language code in the url');
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->statusCodeEquals(200);

    $this->clickLink('English');
    $this->assertSession()->pageTextContains($block->label());
    $this->assertSession()->pageTextContains('50 results found');
    $this->assertSession()->statusCodeEquals(200);

    // Test that keys are properly preserved when switching languages.
    $this->drupalGet('/search');
    $this->submitForm(['keys' => 'bird'], 'Search');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->pageTextContains('Hawk');
    $this->clickLink('Spanish');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->pageTextContains('Halcon');
    $this->clickLink('Dutch');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->pageTextContains('Havik');
    $this->clickLink('English');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1 result found');
    $this->assertSession()->pageTextContains('Hawk');
  }

}
