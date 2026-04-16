<?php

namespace Drupal\Tests\feeds\Kernel\Feeds\Target;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\Utility\Token;
use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;
use Drupal\Tests\feeds\Traits\FeedsMockingTrait;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Exception\TargetValidationException;
use Drupal\feeds\Feeds\Target\File;
use Drupal\feeds\Feeds\Target\FileExistsTrait;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Utility\FileResolver;
use Drupal\user\Entity\Role;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Base class for file field tests.
 */
abstract class FileTestBase extends FeedsKernelTestBase {

  use ProphecyTrait;
  use FeedsMockingTrait;
  use FileExistsTrait;

  /**
   * The entity type manager prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The http client prophecy used in the test.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Token service.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The entity field manager.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Feeds entity finder service.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\feeds\EntityFinderInterface
   */
  protected $entityFinder;

  /**
   * The FeedsTarget plugin being tested.
   *
   * @var \Drupal\feeds\Feeds\Target\File
   */
  protected $targetPlugin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setUpFileFields();
    $this->setUpPrivateFileSystem();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->client = $this->prophesize(ClientInterface::class);
    $this->token = $this->prophesize(Token::class);
    $this->entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    $this->entityFieldManager->getFieldStorageDefinitions('file')->willReturn([]);
    $this->entityFinder = $this->prophesize(EntityFinderInterface::class);
    $this->entityFinder->findEntities(Argument::cetera())->willReturn([]);

    // Made-up entity type that we are referencing to.
    $referenceable_entity_type = $this->prophesize(EntityTypeInterface::class);
    $referenceable_entity_type->getKey('label')->willReturn('filename');
    $this->entityTypeManager->getDefinition('file')->willReturn($referenceable_entity_type)->shouldBeCalled();

    $this->targetPlugin = $this->getTargetPlugin();

    // Role::load fails without installing the user config.
    $this->installConfig(['user']);
    // Give anonymous users permission to access content, so that they can view
    // and download public files. Without this we get an access denied error
    // when trying to import public files.
    Role::load(Role::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Make sure that the private file system can get used.
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * Returns the file target class to instantiate.
   *
   * @return string
   *   The class for the file target plugin.
   */
  abstract protected function getTargetPluginClass();

  /**
   * Returns target definition to pass to the target constructor.
   *
   * @return array
   *   The target definition for the file target.
   */
  abstract protected function getTargetDefinition();

  /**
   * Returns a mocked file target plugin.
   *
   * @param string|null $plugin_class
   *   The plugin class to instantiate or null to instantiate the default.
   *
   * @return \Drupal\feeds\Feeds\Target\File
   *   The mocked file target plugin.
   */
  protected function getTargetPlugin(?string $plugin_class = NULL): File {
    if ($plugin_class === NULL) {
      $plugin_class = $this->getTargetPluginClass();
    }

    $configuration = [
      'feed_type' => $this->createMock(FeedTypeInterface::class),
      'target_definition' => $this->getTargetDefinition(),
    ];

    // Mock the HTTP client in the container so FileResolver service uses it.
    // This must be done before getting the FileResolver service.
    $this->container->set('http_client', $this->client->reveal());

    // Re-instantiate FileResolver service with the mocked HTTP client.
    // This ensures FileResolver uses the mocked client instead of the real one.
    $file_resolver = FileResolver::create($this->container);

    $plugin = $this->getMockBuilder($plugin_class)
      ->onlyMethods(['getDestinationDirectory'])
      ->setConstructorArgs([
        $configuration,
        'file',
        [],
        $this->entityTypeManager->reveal(),
        $this->client->reveal(),
        $this->token->reveal(),
        $this->entityFieldManager->reveal(),
        $this->entityFinder->reveal(),
        $this->container->get('file_system'),
        $this->container->get('file.repository'),
        $this->container->get('config.factory')->get('system.file'),
        $file_resolver,
      ])
      ->getMock();

    $plugin->expects($this->any())
      ->method('getDestinationDirectory')
      ->willReturn('public://');

    return $plugin;
  }

  /**
   * Tests prepareValue() for file target plugins.
   *
   * @param array $expected
   *   The expected values.
   * @param array $values
   *   The values to pass to prepareValue().
   * @param string $expected_exception
   *   (optional) The name of the expected exception class.
   * @param string $expected_exception_message
   *   (optional) The expected exception message.
   *
   * @dataProvider dataProviderPrepareValue
   */
  public function testPrepareValue(array $expected, array $values, $expected_exception = NULL, $expected_exception_message = NULL) {
    // Add in base URL.
    if (isset($values['target_id'])) {
      $file_path = strtr($values['target_id'], [
        '[url]' => $this->resourcesPath(),
      ]);
      $values['target_id'] = strtr($values['target_id'], [
        '[url]' => $this->resourcesUrl(),
      ]);

      // Set guzzle client response.
      if (file_exists($file_path)) {
        $this->client->request('GET', $values['target_id'], Argument::any())->will(function () use ($file_path) {
          return new Response(200, [], file_get_contents($file_path));
        });
      }
      else {
        $this->client->request('GET', $values['target_id'], Argument::any())->will(function () {
          return new Response(404, [], '');
        });
      }
    }

    $plugin = $this->getTargetPlugin();
    $method = $this->getProtectedClosure($plugin, 'prepareValue');

    // Set expected exception if there is one expected.
    if ($expected_exception) {
      $this->expectException($expected_exception);
      if ($expected_exception_message) {
        $expected_exception_message = strtr($expected_exception_message, [
          '[url]' => $this->resourcesUrl(),
        ]);
        $this->expectExceptionMessage($expected_exception_message);
      }
    }

    // Call prepareValue().
    $method(0, $values);

    // Asserts.
    foreach ($expected as $key => $value) {
      $this->assertEquals($value, $values[$key]);
    }
  }

  /**
   * Data provider for testPrepareValue().
   */
  public static function dataProviderPrepareValue() {
    $return = [
      // Empty file target value.
      'empty-file' => [
        'expected' => [],
        'values' => [
          'target_id' => '',
        ],
        'expected_exception' => EmptyFeedException::class,
        'expected_exception_message' => 'The given file value is empty.',
      ],
      // Importing a file url that exists.
      'file-success' => [
        'expected' => [
          'target_id' => 1,
        ],
        'values' => [
          'target_id' => '[url]/assets/attersee.jpeg',
        ],
      ],
      // Importing a file with uppercase extension.
      'file-uppercase' => [
        'expected' => [
          'target_id' => 1,
        ],
        'values' => [
          'target_id' => '[url]/assets/attersee.JPG',
        ],
      ],

      // Importing a file url that does *not* exist.
      'file-not-found' => [
        'expected' => [],
        'values' => [
          'target_id' => '[url]/assets/not-found.jpg',
        ],
        'expected_exception' => TargetValidationException::class,
        'expected_exception_message' => 'Download of <em class="placeholder">[url]/assets/not-found.jpg</em> failed with code 404.',
      ],

      // Importing a file with invalid extension.
      'invalid-extension' => [
        'expected' => [],
        'values' => [
          'target_id' => '[url]/file.foo',
        ],
        'expected_exception' => TargetValidationException::class,
        'expected_exception_message' => 'The file, <em class="placeholder">[url]/file.foo</em>, failed to save because the extension, <em class="placeholder">foo</em>, is not allowed.',
      ],
    ];
    return $return;
  }

  /**
   * Saves a file to the specified destination and creates a database entry.
   *
   * @param string $data
   *   A string containing the contents of the file.
   * @param string|null $destination
   *   (optional) A string containing the destination URI. This must be a stream
   *   wrapper URI. If no value or NULL is provided, a randomized name will be
   *   generated and the file will be saved using Drupal's default files scheme,
   *   usually "public://".
   * @param \Drupal\Core\File\FileExists|int $replace
   *   (optional) The replace behavior when the destination file already exists.
   *   Can be a FileExists enum or legacy integer constant. Possible values:
   *   - FileExists::Replace: Replace the existing file. If a managed file with
   *     the destination name exists, then its database entry will be updated.
   *     If no database entry is found, then a new one will be created.
   *   - FileExists::Rename: (default) Append _{incrementing number} until the
   *     filename is unique.
   *   - FileExists::Error: Do nothing and return FALSE.
   *
   * @return \Drupal\file\FileInterface|false
   *   A file entity, or FALSE on error.
   */
  protected function writeData($data, $destination = NULL, FileExists|int $replace = FileExists::Rename) {
    if (empty($destination)) {
      $destination = \Drupal::config('system.file')->get('default_scheme') . '://';
    }
    // Normalize to FileExists enum if needed.
    if (!$replace instanceof FileExists) {
      $replace = $this->mapLegacyIntToFileExists($replace);
    }
    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');
    try {
      return $fileRepository->writeData($data, $destination, $replace);
    }
    catch (FileException | EntityStorageException $e) {
      return FALSE;
    }
  }

}
