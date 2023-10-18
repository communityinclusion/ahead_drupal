<?php

namespace Drupal\Tests\search_api_saved_searches\Functional;

use Drupal\Component\Utility\Html;
use Drupal\search_api_saved_searches\Entity\SavedSearchAccessControlHandler;
use Drupal\search_api_saved_searches\Entity\SavedSearchType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;
use Drupal\Tests\search_api\Kernel\TestLogger;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests overall functionality of the module.
 *
 * @group search_api_saved_searches
 */
class IntegrationTest extends BrowserTestBase {

  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field_ui',
    // The "language" module is only needed for the moment because of a bug in
    // Core. See https://www.drupal.org/node/3273888.
    'language',
    'search_api_saved_searches',
    'search_api_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A admin user used in this test.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * A non-admin user used in this test.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $registeredUser;

  /**
   * A second non-admin user used in this test.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $registeredUser2;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test users.
    $this->adminUser = $this->createUser([
      SavedSearchAccessControlHandler::ADMIN_PERMISSION,
      'administer search_api_saved_search display',
      'administer search_api_saved_search fields',
      'administer search_api_saved_search form display',
    ]);
    $this->registeredUser = $this->createUser();
    $this->registeredUser2 = $this->createUser();

    // Use the state system collector mail backend.
    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    // Set a logger that will throw exceptions when warnings/errors are logged.
    $logger = new TestLogger('');
    $this->container->set('logger.factory', $logger);
    $this->container->set('logger.channel.search_api', $logger);
    $this->container->set('logger.channel.search_api_saved_searches', $logger);

    // Generate and index example content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->indexItems('database_search_index');

    // Make normal admin UI navigation possible by enabling some common blocks.
    $this->placeBlock('local_actions_block');
    $this->placeBlock('local_tasks_block');
  }

  /**
   * Tests overall functionality of the module.
   *
   * Uses sub-methods to improve readability.
   */
  public function testModule() {
    $this->drupalLogin($this->adminUser);

    $this->configureDefaultType();
    $this->addNewType();
    $this->checkFunctionalityAnonymous();
    $this->checkFunctionalityRegistered();
    $this->checkAccessChecks();
    $this->deleteType();
  }

  /**
   * Checks and edits the default saved search type.
   */
  protected function configureDefaultType() {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/config/search/search-api-saved-searches');

    $assert_session->pageTextContains('Saved searches');
    $assert_session->pageTextContains('Default');
    $assert_session->pageTextContains('A default type created automatically at module installation.');

    $this->clickLink('Edit');

    $activation_mail = <<<'END'
- Activate: [search-api-saved-search:activate-url]
- View: [search-api-saved-search:view-url]
- Edit: [search-api-saved-search:edit-url]
- Delete: [search-api-saved-search:delete-url]
END;
    $edit = [
      'label' => 'My test default',
      'status' => TRUE,
      'options[displays][default]' => '0',
      'options[displays][selected][views_page:search_api_test_view__page_1]' => TRUE,
      'notification_plugins[email]' => TRUE,
      'notification_configs[email][activate][body]' => $activation_mail,
      'options[max_results]' => 13,
      'options[description]' => 'Description for the default type.',
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('Your settings have been saved.');

    $type = SavedSearchType::load('default');
    $expected = [
      'displays' => [
        'default' => FALSE,
        'selected' => [
          'views_page:search_api_test_view__page_1',
        ],
      ],
      'max_results' => 13,
      'date_field' => [
        'database_search_index' => '',
      ],
      'description' => 'Description for the default type.',
    ];
    $this->assertEquals($expected, $type->getOptions());

    $this->clickLink('Manage form display');
    $assert_session->pageTextContains('Label');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');
    $assert_session->pageTextContains('Fulltext keywords');
    $assert_session->checkboxChecked('display_modes_custom[create]');

    // Enable the "Fulltext keywords" field.
    $edit = [
      'fields[search_keywords][region]' => 'content',
      'fields[search_keywords][weight]' => 10,
    ];
    $this->submitForm($edit, 'Save');

    $this->clickLink('Create');
    $assert_session->pageTextContains('Label');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');
    $assert_session->pageTextContains('Fulltext keywords');

    $this->placeBlock('search_api_saved_searches', [
      'label' => 'Default saved searches block',
      'type' => 'default',
    ]);
  }

  /**
   * Adds a new saved search type.
   */
  protected function addNewType() {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/config/search/search-api-saved-searches');

    $this->clickLink('Add saved search type');

    $edit = [
      'label' => 'Foo &amp; Bar',
      'id' => 'foobar',
      'description' => 'Some description text for type <em>Foo &amp;&amp; Bar</em>.',
      'status' => TRUE,
      'options[displays][default]' => TRUE,
      'options[displays][selected][views_page:search_api_test_view__page_1]' => TRUE,
      'options[displays][selected][views_page:search_api_test_sorts__page_1]' => TRUE,
      'notification_plugins[email]' => TRUE,
      'options[max_results]' => '',
      'options[description]' => 'Description for the foobar type.',
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('Please configure the used notification methods.');
    $this->assertNull(SavedSearchType::load('foobar'));
    $activation_mail = <<<'END'
- Activate: [search-api-saved-search:activate-url]
- View: [search-api-saved-search:view-url]
- Edit: [search-api-saved-search:edit-url]
- Delete: [search-api-saved-search:delete-url]
END;
    $edit = [
      'notification_configs[email][registered_choose_mail]' => TRUE,
      'notification_configs[email][activate][body]' => $activation_mail,
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('Your settings have been saved.');
    $this->assertNotNull(SavedSearchType::load('foobar'));

    $this->clickLink('Manage form display');
    $assert_session->pageTextContains('Label');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');
    $assert_session->pageTextContains('Fulltext keywords');
    $assert_session->checkboxChecked('display_modes_custom[create]');

    $this->clickLink('Create');
    $assert_session->pageTextContains('Label');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');
    $assert_session->pageTextContains('Fulltext keywords');

    $this->placeBlock('search_api_saved_searches', [
      'label' => 'Foo &amp; Bar saved searches block',
      'type' => 'foobar',
    ]);

    $this->drupalGet('admin/config/search/search-api-saved-searches');
    $this->assertOnlyEscaped('Foo &amp; Bar');
    $assert_session->responseContains('Some description text for type <em>Foo &amp;&amp; Bar</em>.');
  }

  /**
   * Checks functionality for anonymous users.
   */
  protected function checkFunctionalityAnonymous() {
    $assert_session = $this->assertSession();

    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    // Anonymous users don't have permission yet to use saved searches.
    $this->drupalGet('search-api-test');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');
    $this->drupalGet('search-api-test-search-view-caching-none');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');
    $this->drupalGet('search-api-test-sorts');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');

    // Grant the permissions.
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->grantPermissions($role, [
      'use default search_api_saved_searches',
      'use foobar search_api_saved_searches',
    ]);

    // Now check that there are the expected blocks on all three search pages.
    $this->drupalGet('search-api-test', ['query' => ['search_api_fulltext' => 'foo']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Default saved searches block');
    $assert_session->pageTextContains('Description for the default type.');
    $assert_session->pageTextNotContains('Foo &amp; Bar saved searches block');
    $assert_session->pageTextNotContains('Description for the foobar type.');

    $edit = [
      'label[0][value]' => 'First saved search',
      'notify_interval' => '3600',
      'mail[0][value]' => 'test@example.net',
    ];
    $this->submitForm($edit, 'Save search');
    $assert_session->pageTextContains('Your saved search was successfully created.');
    $assert_session->pageTextContains('You will soon receive an e-mail with a confirmation link to activate it.');

    $this->drupalGet('search-api-test-search-view-caching-none', ['query' => ['search_api_fulltext' => 'bar']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Foo &amp; Bar saved searches block');
    $assert_session->pageTextContains('Description for the foobar type.');
    $assert_session->pageTextNotContains('Default saved searches block');
    $assert_session->pageTextNotContains('Description for the default type.');

    $edit = [
      'label[0][value]' => 'Second saved search',
      'notify_interval' => '86400',
      'mail[0][value]' => 'foobar@example.net',
    ];
    $this->submitForm($edit, 'Save search');
    $assert_session->pageTextContains('Your saved search was successfully created.');
    $assert_session->pageTextContains('You will soon receive an e-mail with a confirmation link to activate it.');

    $this->drupalGet('search-api-test-sorts');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');

    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $second_mail = array_pop($captured_emails);
    $first_mail = array_pop($captured_emails);

    $this->assertEquals('test@example.net', $first_mail['to']);
    $this->assertEquals('foobar@example.net', $second_mail['to']);

    $regex = '#- (\w+):\s+(\S+)#';
    $this->assertEquals(4, preg_match_all($regex, $first_mail['body'], $matches, PREG_SET_ORDER));
    $first_mail_urls = [];
    foreach ($matches as $match) {
      $this->assertStringContainsString('token=', $match[2], "{$match[1]} URL for first saved search doesn't include a token.");
      $first_mail_urls[$match[1]] = $match[2];
    }
    $this->assertEquals(4, preg_match_all($regex, $second_mail['body'], $matches, PREG_SET_ORDER));
    $second_mail_urls = [];
    foreach ($matches as $match) {
      $this->assertStringContainsString('token=', $match[2], "{$match[1]} URL for second saved search doesn't include a token.");
      $second_mail_urls[$match[1]] = $match[2];
    }

    // Check URLs for first saved search.
    $this->drupalGet($first_mail_urls['Activate']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Your saved search was successfully activated.');
    $this->drupalGet($first_mail_urls['Activate']);
    $assert_session->statusCodeEquals(404);

    $this->drupalGet($first_mail_urls['View']);
    $assert_session->statusCodeEquals(200);
    $search_url = $this->buildUrl('search-api-test', ['query' => ['search_api_fulltext' => 'foo']]);
    $assert_session->addressEquals($search_url);

    $this->drupalGet($first_mail_urls['Edit']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Edit saved search First saved search');
    $assert_session->pageTextContains('Fulltext keywords');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');

    $this->drupalGet($first_mail_urls['Delete']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Do you really want to delete this saved search?');

    // Check URLs for second saved search.
    $this->drupalGet($second_mail_urls['Activate']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Your saved search was successfully activated.');
    $this->drupalGet($second_mail_urls['Activate']);
    $assert_session->statusCodeEquals(404);

    $this->drupalGet($second_mail_urls['View']);
    $assert_session->statusCodeEquals(200);
    $search_url = $this->buildUrl('search-api-test-search-view-caching-none', ['query' => ['search_api_fulltext' => 'bar']]);
    $assert_session->addressEquals($search_url);

    $this->drupalGet($second_mail_urls['Edit']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Edit saved search Second saved search');
    $assert_session->pageTextNotContains('Fulltext keywords');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');

    $this->drupalGet($second_mail_urls['Delete']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Do you really want to delete this saved search?');
  }

  /**
   * Checks functionality for registered users.
   */
  protected function checkFunctionalityRegistered() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->registeredUser);

    // Registered users don't have permission yet to use saved searches.
    $this->drupalGet('search-api-test');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');
    $this->drupalGet('search-api-test-search-view-caching-none');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');
    $this->drupalGet('search-api-test-sorts');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');

    // Grant the permissions.
    $role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->grantPermissions($role, [
      'use default search_api_saved_searches',
      'use foobar search_api_saved_searches',
    ]);

    // Now check that there are the expected blocks on all three search pages.
    $this->drupalGet('search-api-test', ['query' => ['search_api_fulltext' => 'foo']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Default saved searches block');
    $assert_session->pageTextContains('Description for the default type.');
    $assert_session->pageTextNotContains('Foo &amp; Bar saved searches block');
    $assert_session->pageTextNotContains('Description for the foobar type.');
    $assert_session->pageTextNotContains('E-mail');

    $edit = [
      'label[0][value]' => 'First saved search',
      'notify_interval' => '3600',
    ];
    $this->submitForm($edit, 'Save search');
    $assert_session->pageTextContains('Your saved search was successfully created.');
    $assert_session->pageTextNotContains('You will soon receive an e-mail with a confirmation link to activate it.');

    $this->drupalGet('search-api-test-search-view-caching-none', ['query' => ['search_api_fulltext' => 'bar']]);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Foo &amp; Bar saved searches block');
    $assert_session->pageTextContains('Description for the foobar type.');
    $assert_session->pageTextNotContains('Default saved searches block');
    $assert_session->pageTextNotContains('Description for the default type.');

    $edit = [
      'label[0][value]' => 'Second saved search',
      'notify_interval' => '86400',
      'mail[0][value]' => 'testuser@example.com',
    ];
    $this->submitForm($edit, 'Save search');
    $assert_session->pageTextContains('Your saved search was successfully created.');
    $assert_session->pageTextContains('You will soon receive an e-mail with a confirmation link to activate it.');

    $this->drupalGet('search-api-test-sorts');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('Description for the');

    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $mail = array_pop($captured_emails);

    $this->assertEquals('testuser@example.com', $mail['to']);

    $regex = '#- (\w+):\s+(\S+)#';
    $this->assertEquals(4, preg_match_all($regex, $mail['body'], $matches, PREG_SET_ORDER));
    $mail_urls = [];
    foreach ($matches as $match) {
      if ($match[1] === 'Activate') {
        $this->assertStringContainsString('token=', $match[2], "{$match[1]} URL for saved search doesn't include a token.");
      }
      else {
        $this->assertStringNotContainsString('token=', $match[2], "{$match[1]} URL for saved search unnecessarily includes a token.");
      }
      $mail_urls[$match[1]] = $match[2];
    }

    // Check URLs for the saved search we got in the mail.
    $this->drupalGet($mail_urls['Activate']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Your saved search was successfully activated.');
    $this->drupalGet($mail_urls['Activate']);
    $assert_session->statusCodeEquals(404);

    $this->drupalGet($mail_urls['View']);
    $assert_session->statusCodeEquals(200);
    $search_url = $this->buildUrl('search-api-test-search-view-caching-none', ['query' => ['search_api_fulltext' => 'bar']]);
    $assert_session->addressEquals($search_url);

    $this->drupalGet($mail_urls['Edit']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Edit saved search Second saved search');
    $assert_session->pageTextNotContains('Fulltext keywords');
    $assert_session->pageTextContains('Notification interval');
    $assert_session->pageTextContains('E-mail');

    $this->drupalGet($mail_urls['Delete']);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Do you really want to delete this saved search?');
  }

  /**
   * Makes sure access checks work correctly.
   */
  protected function checkAccessChecks() {
    $assert_session = $this->assertSession();

    // Make sure we really have all the expected saved searches present, to
    // avoid confusing assertion failures later.
    $total = \Drupal::entityQuery('search_api_saved_search')
      ->count()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEquals(4, $total);

    $user1_uid = $this->registeredUser->id();
    $tests = [
      'anonymous' => [
        'account' => NULL,
        'access' => [],
      ],
      'admin' => [
        'account' => $this->adminUser,
        'access' => [0, $user1_uid],
      ],
      '1st registered' => [
        'account' => $this->registeredUser,
        'access' => [$user1_uid],
      ],
      '2nd registered' => [
        'account' => $this->registeredUser2,
        'access' => [],
      ],
    ];
    foreach ($tests as $key => $info) {
      if ($this->loggedInUser) {
        $this->drupalLogout();
      }
      if ($info['account']) {
        $this->drupalLogin($info['account']);
      }

      foreach ([0, $user1_uid] as $uid) {
        $this->drupalGet("user/$uid/saved-searches");
        if (in_array($uid, $info['access'])) {
          $assert_session->pageTextNotContains('The requested page could not be found.');
        }
        else {
          $assert_session->pageTextContains('The requested page could not be found.');
        }
      }
    }
  }

  /**
   * Deletes the "Foobar" saved search type.
   */
  protected function deleteType() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/search/search-api-saved-searches/type/foobar/edit');
    $this->clickLink('Delete');

    $count_foobar_searches = \Drupal::entityQuery('search_api_saved_search')
      ->condition('type', 'foobar')
      ->count()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertGreaterThan(0, $count_foobar_searches);

    $assert_session->pageTextContains('Do you really want to delete this saved search type?');
    if ($count_foobar_searches > 1) {
      $message = "Foo &amp; Bar is used by $count_foobar_searches saved searches on your site. You cannot remove this saved search type until you have removed all of the Foo &amp; Bar saved searches.";
    }
    else {
      $message = 'Foo &amp; Bar is used by 1 saved search on your site. You cannot remove this saved search type until you have removed all of the Foo &amp; Bar saved searches.';
    }
    $assert_session->pageTextContains($message);
    $this->assertOnlyEscaped('Foo &amp; Bar');

    // Delete all saved searches of type "foobar".
    $this->drupalGet('admin/content/saved-searches');
    $edit = ['type[]' => 'foobar'];
    for ($i = 0; $i < $count_foobar_searches; ++$i) {
      $this->submitForm($edit, 'Apply');
      $this->clickLink('Delete');
      $this->submitForm([], 'Delete');
      $assert_session->pageTextContains('The saved search was successfully deleted.');
      $assert_session->addressEquals($this->buildUrl('admin/content/saved-searches'));
    }

    $this->drupalGet('admin/config/search/search-api-saved-searches/type/foobar/delete');
    $assert_session->pageTextContains('Do you really want to delete this saved search type?');
    $assert_session->pageTextContains('This action cannot be undone.');
    $assert_session->pageTextContains('Configuration deletions');
    $assert_session->pageTextContains('The listed configuration will be deleted.');
    $assert_session->pageTextContains('Block');
    $this->assertOnlyEscaped('Foo &amp; Bar saved searches block');
    $assert_session->pageTextContains('Entity form display');
    $assert_session->pageTextContains('search_api_saved_search.foobar.create');

    $this->submitForm([], 'Delete');
    $assert_session->pageTextContains('The saved search type was successfully deleted.');
    $this->assertNull(SavedSearchType::load('foobar'));
  }

  /**
   * Asserts that the given string is properly escaped on output.
   *
   * Will check that the string is present in its escaped form in the current
   * page's output (of the default session) and that it's neither present
   * unescaped nor double-escaped.
   *
   * @param string $string
   *   The string for which to test proper escaping.
   */
  protected function assertOnlyEscaped(string $string) {
    $assert_session = $this->assertSession();

    $escaped = Html::escape($string);
    $double_escaped = Html::escape($escaped);
    $assert_session->responseContains($escaped);
    if ($string !== $escaped) {
      $assert_session->responseNotContains($string);
    }
    if ($double_escaped !== $escaped) {
      $assert_session->responseNotContains($double_escaped);
    }
  }

}
