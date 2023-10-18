<?php

namespace Drupal\Tests\search_api_saved_searches\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSession;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api_saved_searches\Entity\SavedSearch;
use Drupal\search_api_saved_searches\Entity\SavedSearchType;
use Drupal\search_api_test\Plugin\search_api\backend\TestBackend;
use Drupal\search_api_test\PluginTestTrait;
use Drupal\Tests\search_api\Kernel\TestLogger;
use Drupal\Tests\search_api\Kernel\TestTimeService;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests whether mails are translated correctly.
 *
 * @group search_api_saved_searches
 *
 * @see \Drupal\search_api_saved_searches\Plugin\search_api_saved_searches\notification\Email
 */
class EmailTranslationTest extends KernelTestBase {

  use PluginTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_translation',
    'entity_test',
    'language',
    'options',
    'search_api',
    'search_api_saved_searches',
    'search_api_test',
    'system',
    'user',
  ];

  /**
   * The test index used.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The ID of the test index.
   *
   * @var string
   */
  protected $indexId = 'test';

  /**
   * The test time service.
   *
   * @var \Drupal\Tests\search_api\Kernel\TestTimeService
   */
  protected $timeService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('search_api_saved_searches', ['search_api_saved_searches_old_results']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('search_api_saved_search');
    $this->installEntitySchema('user');
    $this->installConfig([
      'language',
      'search_api',
      'search_api_saved_searches',
      'user',
    ]);

    // Create a second language and sets up session parameter-based language
    // negotiation, so we can more easily switch.
    ConfigurableLanguage::create(['id' => 'xx', 'label' => 'XX'])->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', [
        LanguageNegotiationSession::METHOD_ID => 0,
      ])
      ->save();

    // Use the state system collector mail backend.
    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    // Set some more site settings used in the test.
    $this->config('system.site')
      ->set('name', 'Saved Searches Test')
      ->set('mail', 'admin@example.net')
      ->save();
    $this->config('user.settings')
      ->set('anonymous', 'Chuck Norris')
      ->save();

    // Create a search server and index.
    Server::create([
      'id' => 'test',
      'backend' => 'search_api_test',
    ])->save();
    $this->index = Index::create([
      'id' => $this->indexId,
      'server' => 'test',
      'datasource_settings' => [
        'entity:entity_test_mulrev_changed' => [],
      ],
    ]);
    $this->index->save();

    // Add mail subjects and bodies for the default saved search type.
    $type = SavedSearchType::load('default');
    $type->getNotificationPlugin('email')->setConfiguration([
      'registered_choose_mail' => TRUE,
      'activate' => [
        'send' => TRUE,
        'title' => 'Activation mail subject (en)',
        'body' => 'Activation mail body (en)',
      ],
      'notification' => [
        'title' => 'Notification mail subject (en)',
        'body' => 'Notification mail body (en)',
      ],
    ]);
    $type->save();

    // Add a translation for both subjects and bodies.
    $config_translation = \Drupal::languageManager()
      ->getLanguageConfigOverride('xx', $type->getConfigDependencyName());
    $config_translation->set('notification_settings.email.activate.title', 'Activation mail subject (xx)');
    $config_translation->set('notification_settings.email.activate.body', 'Activation mail body (xx)');
    $config_translation->set('notification_settings.email.notification.title', 'Notification mail subject (xx)');
    $config_translation->set('notification_settings.email.notification.body', 'Notification mail body (xx)');
    $config_translation->save();
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

    // Use a test time service to easily manipulate the "created" date.
    $this->timeService = new TestTimeService();
    $container->set('datetime.time', $this->timeService);
  }

  /**
   * Verifies that e-mails are translated correctly.
   *
   * @param string|null $user_langcode
   *   The preferred langcode to set for the saved search owner, or NULL to use
   *   the anonymous user.
   * @param string $site_langcode
   *   The current site language to use.
   * @param string $expected_mail_langcode
   *   The expected language code for e-mails.
   *
   * @dataProvider emailTranslationsTestDataProvider
   */
  public function testEmailTranslations(?string $user_langcode, string $site_langcode, string $expected_mail_langcode): void {
    // Set up the user which will be the saved search owner.
    if ($user_langcode === NULL) {
      $owner = User::create([
        'uid' => 0,
        'name' => '',
      ]);
      $owner->save();
      $this->assertTrue($owner->isAnonymous());
    }
    else {
      // @todo Use a named $values argument once we depend on PHP 8.0+.
      $owner = $this->createUser([], NULL, FALSE, [
        'uid' => 2,
        'preferred_langcode' => $user_langcode,
      ]);
      $this->assertEquals($user_langcode, $owner->getPreferredLangcode(FALSE));
    }
    \Drupal::currentUser()->setAccount($owner);
    $this->container->get('language_negotiator')->setCurrentUser($owner);

    // Set the current site language.
    $request = \Drupal::request();
    $request->query->set('language', $site_langcode);
    \Drupal::languageManager()->reset();
    $this->assertEquals($site_langcode, \Drupal::languageManager()->getCurrentLanguage()->getId());

    // Create the saved search.
    $query = $this->index->query()->setLanguages([$site_langcode]);
    $search = SavedSearch::create([
      'type' => 'default',
      'index_id' => $this->indexId,
      'query' => $query,
      'label' => 'Test search',
      'mail' => 'foo@example.org',
      'notify_interval' => 3600,
    ]);
    $search->save();

    // Make sure nothing went wrong so far.
    $this->assertEquals($owner->id(), $search->getOwnerId());
    $this->assertEquals($site_langcode, $search->getLangcode());
    $this->assertFalse($search->get('status')->value);

    // Retrieve the sent activation mail and check the language it used.
    $this->container->get('search_api_saved_searches.email_queue')->destruct();
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    \Drupal::state()->delete('system.test_mail_collector');
    $this->assertCount(1, $captured_emails);
    $activation_mail = reset($captured_emails);
    $this->assertEquals("Activation mail subject ($expected_mail_langcode)", $activation_mail['subject']);
    $this->assertEquals("Activation mail body ($expected_mail_langcode)", trim($activation_mail['body']));

    // Activate the search and trigger a notification mail.
    $search->set('status', TRUE)->save();
    $this->timeService->advanceTime(3600);
    $search_method = function (TestBackend $backend, QueryInterface $query) use ($site_langcode) {
      $this->assertEquals([$site_langcode], $query->getLanguages());
      $query->getResults()
        ->setResultCount(1)
        ->setResultItems([new Item($this->index, 'foo')]);
    };
    $this->setMethodOverride('backend', 'search', $search_method);
    $this->container->get('search_api_saved_searches.new_results_check')
      ->checkAll();

    // Retrieve the sent notification mail and check the language it used.
    $this->container->get('search_api_saved_searches.email_queue')->destruct();
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    \Drupal::state()->delete('system.test_mail_collector');
    $this->assertCount(1, $captured_emails);
    $notification_mail = reset($captured_emails);
    $this->assertEquals("Notification mail subject ($expected_mail_langcode)", $notification_mail['subject']);
    $this->assertEquals("Notification mail body ($expected_mail_langcode)", trim($notification_mail['body']));
  }

  /**
   * Provides test data sets for testEmailTranslations().
   *
   * @return array[]
   *   An associative array of argument arrays for testEmailTranslations(),
   *   keyed by the data set labels.
   *
   * @see testEmailTranslations()
   */
  public function emailTranslationsTestDataProvider(): array {
    return [
      'anonymous, site en' => [NULL, 'en', 'en'],
      'anonymous, site xx' => [NULL, 'xx', 'xx'],
      'user en, site en' => ['en', 'en', 'en'],
      'user en, site xx' => ['en', 'xx', 'en'],
      'user xx, site en' => ['xx', 'en', 'xx'],
      'user xx, site xx' => ['xx', 'xx', 'xx'],
      'user no preference, site en' => ['', 'en', 'en'],
      'user no preference, site xx' => ['', 'xx', 'xx'],
    ];
  }

}
