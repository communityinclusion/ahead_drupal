<?php

namespace Drupal\Tests\search_api_saved_searches\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_saved_searches\Entity\SavedSearch;
use Drupal\search_api_saved_searches\Entity\SavedSearchType;
use Drupal\Tests\search_api\Kernel\TestLogger;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Verifies that the module reacts correctly to user CRUD operations.
 *
 * @group search_api_saved_searches
 */
class UserCrudReactionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'search_api',
    'search_api_saved_searches',
    'system',
    'user',
  ];

  /**
   * The test user used in these tests.
   */
  protected UserInterface $testUser;

  /**
   * Saved searches created for testing.
   *
   * @var \Drupal\search_api_saved_searches\SavedSearchInterface[]
   */
  protected array $savedSearches = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('search_api_saved_searches');
    $this->installEntitySchema('user');
    $this->installEntitySchema('search_api_saved_search');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('search_api_saved_searches', 'search_api_saved_searches_old_results');

    User::create([
      'uid' => 0,
      'name' => '',
      'status' => FALSE,
    ])->save();

    // Create the anonymous role and a test role and grant the permission to
    // create saved searches to both of them.
    $permission = 'use default search_api_saved_searches';
    Role::create([
      'id' => Role::ANONYMOUS_ID,
      'label' => 'Anonymous user',
      'permissions' => [$permission],
    ])->save();
    Role::create([
      'id' => 'test_role',
      'label' => 'Test role',
      'permissions' => [$permission],
    ])->save();

    // Add a test user that will become the owner of our saved searches.
    $this->testUser = User::create([
      'uid' => 5,
      'name' => 'test',
      'status' => TRUE,
      'mail' => 'test@example.com',
      'roles' => ['test_role'],
    ]);
    $this->testUser->save();

    // Add a saved search for that user.
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'default',
      'uid' => $this->testUser->id(),
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
      'mail' => $this->testUser->getEmail(),
    ]);

    // Add some anonymously created saved searches.
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'default',
      'uid' => 0,
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
      'mail' => 'foo@example.com',
    ]);
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'default',
      'uid' => 0,
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
      'mail' => 'foo@example.com',
    ]);
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'default',
      'uid' => 0,
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
      'mail' => 'bar@example.com',
    ]);
    foreach ($this->savedSearches as $search) {
      $search->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // Set a logger that will throw exceptions when warnings/errors are logged.
    $logger = new TestLogger('');
    $container->set('logger.factory', $logger);
    $container->set('logger.channel.search_api', $logger);
    $container->set('logger.channel.search_api_saved_searches', $logger);
  }

  /**
   * Verifies correct reaction to the creation of a new user.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_user_insert()
   *
   * @dataProvider dataSetProvider
   */
  public function testUserInsert(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
    }

    $account = User::create([
      'name' => 'foo',
      'status' => TRUE,
      'mail' => 'foo@example.com',
    ]);
    $account->save();

    // Creating a new user claimed all anonymously created alerts with the same
    // email address.
    $this->reloadSavedSearches();
    $expected_owner = $disable_email_plugin ? 0 : $account->id();
    $this->assertEquals($expected_owner, $this->savedSearches[1]->getOwnerId());
    $this->assertEquals($expected_owner, $this->savedSearches[2]->getOwnerId());
    $this->assertEquals(0, $this->savedSearches[3]->getOwnerId());

    User::create([
      'name' => 'bar',
      'status' => FALSE,
      'mail' => 'bar@example.com',
    ])->save();

    // Creating an inactive user didn't affect any alerts.
    $this->reloadSavedSearches();
    $this->assertEquals($expected_owner, $this->savedSearches[1]->getOwnerId());
    $this->assertEquals($expected_owner, $this->savedSearches[2]->getOwnerId());
    $this->assertEquals(0, $this->savedSearches[3]->getOwnerId());
  }

  /**
   * Verifies correct reaction to the activation of a user account.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_user_update()
   * @see _search_api_saved_searches_claim_anonymous_searches()
   *
   * @dataProvider dataSetProvider
   */
  public function testUserActivate(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
    }

    $account = User::create([
      'name' => 'foo',
      'status' => FALSE,
      'mail' => 'foo@example.com',
    ]);
    $account->save();

    // Creating an inactive user didn't affect any alerts.
    $this->reloadSavedSearches();
    $this->assertEquals(0, $this->savedSearches[1]->getOwnerId());
    $this->assertEquals(0, $this->savedSearches[2]->getOwnerId());
    $this->assertEquals(0, $this->savedSearches[3]->getOwnerId());

    $account->activate()->save();

    // Once activated, all anonymously created alerts with the same email
    // address are moved to that user.
    $this->reloadSavedSearches();
    $expected_owner = $disable_email_plugin ? 0 : $account->id();
    $this->assertEquals($expected_owner, $this->savedSearches[1]->getOwnerId());
    $this->assertEquals($expected_owner, $this->savedSearches[2]->getOwnerId());
    $this->assertEquals(0, $this->savedSearches[3]->getOwnerId());
  }

  /**
   * Verifies correct reaction to the deactivation of a user account.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_user_update()
   * @see _search_api_saved_searches_deactivate_searches()
   *
   * @dataProvider dataSetProvider
   */
  public function testUserDeactivate(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
    }

    $this->testUser->block()->save();
    $this->reloadSavedSearches();
    $search = array_shift($this->savedSearches);
    $this->assertEquals(-1, $search->get('notify_interval')->value);

    // Verify that the other alerts were unaffected.
    foreach ($this->savedSearches as $search) {
      $this->assertEquals(3600 * 24, $search->get('notify_interval')->value);
    }
  }

  /**
   * Verifies correct reaction to a user account losing a role.
   *
   * @see search_api_saved_searches_user_update()
   * @see _search_api_saved_searches_deactivate_searches()
   */
  public function testUserLoseRole() {
    // Remove the test role from our test user.
    $this->testUser->removeRole('test_role');
    $this->testUser->save();
    // Make sure this disabled the saved search.
    $this->reloadSavedSearches();
    $search = array_shift($this->savedSearches);
    $this->assertEquals(-1, $search->get('notify_interval')->value);

    // Verify that the other alerts were unaffected.
    foreach ($this->savedSearches as $search) {
      $this->assertEquals(3600 * 24, $search->get('notify_interval')->value);
    }
  }

  /**
   * Verifies correct reaction to a role losing a permission.
   *
   * This cannot be (easily) handled by a hook so we instead check for this when
   * checking alerts for new results.
   *
   * @see \Drupal\search_api_saved_searches\Service\NewResultsCheck::checkAll()
   */
  public function testRoleLosePermission() {
    // Remove the permission to saved searches from our test role.
    $role = Role::load('test_role');
    $role->revokePermission('use default search_api_saved_searches');
    $role->save();

    // Make sure the test search will be picked up by the new results check. The
    // "next_execution" field is automatically re-computed when saving a search,
    // so we need to do this by changing the "last_executed" field to something
    // more than a day ago.
    $search = reset($this->savedSearches);
    $time = \Drupal::time()->getRequestTime();
    $search->set('last_executed', $time - 86400 - 10);
    $search->save();
    $this->reloadSavedSearches();
    $search = reset($this->savedSearches);
    $this->assertLessThan($time, $search->get('next_execution')->value);

    // Do a "new results" check.
    \Drupal::getContainer()->get('search_api_saved_searches.new_results_check')
      ->checkAll();

    // Make sure the saved search was disabled.
    $this->reloadSavedSearches();
    $search = reset($this->savedSearches);
    $this->assertEquals(-1, $search->get('notify_interval')->value);
  }

  /**
   * Verifies correct reaction to the deletion of a user account.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_user_delete()
   *
   * @dataProvider dataSetProvider
   */
  public function testUserDelete(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
    }

    $this->testUser->delete();
    $this->reloadSavedSearches();
    $search = array_shift($this->savedSearches);
    $this->assertEmpty($search);

    // Verify that other alerts were unaffected.
    $this->reloadSavedSearches();
    foreach ($this->savedSearches as $search) {
      $this->assertNotEmpty($search);
    }
  }

  /**
   * Verifies correct reaction to the deletion of a search index.
   *
   * Since the underlying function called is the same as for user deletion (and
   * there are no other reactions to index CRUD events), this is tested as part
   * of this test case.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_search_api_index_delete()
   *
   * @dataProvider dataSetProvider
   */
  public function testIndexDelete(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
      // Also need to reload the saved searches since re-saving search #0 below
      // will otherwise lead to an exception.
      $this->reloadSavedSearches();
    }

    $this->installConfig(['search_api']);
    $index = Index::create([
      'id' => 'test',
    ]);
    $index->save();

    $this->savedSearches[0]->set('index_id', 'test')->save();
    $index->delete();

    $this->reloadSavedSearches();
    $this->assertEmpty($this->savedSearches[0]);
    $this->assertNotEmpty($this->savedSearches[1]);
  }

  /**
   * Verifies correct reaction to a user changing their mail address.
   *
   * Tested on behalf of the "Email" notification plugin.
   *
   * @param bool $disable_email_plugin
   *   Whether or not to disable the "Email" notification plugin.
   *
   * @see search_api_saved_searches_user_update()
   * @see \Drupal\search_api_saved_searches\Plugin\search_api_saved_searches\notification\Email::onUserUpdate()
   *
   * @dataProvider dataSetProvider
   */
  public function testUserMailChange(bool $disable_email_plugin): void {
    if ($disable_email_plugin) {
      SavedSearchType::load('default')
        ->set('notification_settings', [])
        ->save();
    }

    // Add a second saved search type that doesn't use the "Email" notification
    // plugin.
    SavedSearchType::create([
      'id' => 'non_default',
      'status' => TRUE,
      'label' => 'Non-default',
    ])->save();

    // Add a saved search for the test user using a different mail address.
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'default',
      'uid' => $this->testUser->id(),
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
      'mail' => 'foobar@example.com',
    ]);
    end($this->savedSearches)->save();
    // Add a saved search with a different type.
    $this->savedSearches[] = SavedSearch::create([
      'type' => 'non_default',
      'uid' => $this->testUser->id(),
      'status' => TRUE,
      'notify_interval' => 3600 * 24,
    ]);
    end($this->savedSearches)->save();

    // Now change the user's email address and see what happens.
    $this->testUser->setEmail('test@example.net')->save();

    if (!$disable_email_plugin) {
      $this->reloadSavedSearches();
      $this->assertEquals('test@example.net', $this->savedSearches[0]->get('mail')->value);
      $this->assertEquals('foobar@example.com', $this->savedSearches[4]->get('mail')->value);
    }
  }

  /**
   * Provides test data sets for test methods in this class.
   *
   * @return array
   *   An associative array of test data sets, keyed by data set label.
   */
  public function dataSetProvider(): array {
    return [
      'default' => [FALSE],
      'email plugin disabled' => [TRUE],
    ];
  }

  /**
   * Reloads the saved searches in $this->savedSearches.
   */
  protected function reloadSavedSearches(): void {
    foreach ($this->savedSearches as $i => $search) {
      $this->savedSearches[$i] = SavedSearch::load($search->id());
    }
  }

}
