<?php

namespace Drupal\Tests\feeds\Unit\Utility;

use Psr\Http\Message\RequestInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\feeds\EntityFinderInterface;
use Drupal\feeds\Exception\DownloadException;
use Drupal\feeds\Exception\InvalidFileExtensionException;
use Drupal\feeds\Utility\FileResolver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\feeds\Utility\FileResolver
 * @group feeds
 */
class FileResolverTest extends FeedsUnitTestCase {

  use ProphecyTrait;

  /**
   * The HTTP client prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The entity type manager prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity finder prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\feeds\EntityFinderInterface
   */
  protected $entityFinder;

  /**
   * The file system prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The stream wrapper manager prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The file repository prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The file storage prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The current user prophecy.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface|\Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->client = $this->prophesize(ClientInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityFinder = $this->prophesize(EntityFinderInterface::class);
    $this->fileSystem = $this->prophesize(FileSystemInterface::class);
    $this->streamWrapperManager = $this->prophesize(StreamWrapperManagerInterface::class);
    $this->fileRepository = $this->prophesize(FileRepositoryInterface::class);
    $this->fileStorage = $this->prophesize(EntityStorageInterface::class);
    $this->currentUser = $this->prophesize(AccountInterface::class);

    $this->entityTypeManager->getStorage('file')
      ->willReturn($this->fileStorage->reveal());

    // Default current user ID is 1.
    $this->currentUser->id()->willReturn(1);
  }

  /**
   * Creates a FileResolver instance.
   *
   * @return \Drupal\feeds\Utility\FileResolver
   *   The FileResolver instance.
   */
  protected function createFileResolver() {
    return new FileResolver(
      $this->client->reveal(),
      $this->entityTypeManager->reveal(),
      $this->entityFinder->reveal(),
      $this->fileSystem->reveal(),
      $this->streamWrapperManager->reveal(),
      $this->currentUser->reveal(),
      $this->fileRepository->reveal()
    );
  }

  /**
   * Tests resolving a file from a URL.
   *
   * Flow: Downloads file content from URL, saves it to destination directory,
   * and creates a new file entity. No existing file at destination, no existing
   * file entity.
   *
   * @covers ::resolve
   * @covers ::isUrl
   */
  public function testResolveWithUrl() {
    $resolver = $this->createFileResolver();

    $content = 'test file content';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willReturn($response);

    // Mock realpath to return a real path so file_exists() works.
    // When realpath returns NULL, file_exists() is called on the original
    // string, which fails for stream wrappers. So we ensure realpath returns a
    // path.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn('/tmp/public/files/file.txt');
    // For other calls, return NULL to indicate file doesn't exist.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(1);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    $this->fileRepository->writeData($content, 'public://files/file.txt', FileExists::Replace)
      ->willReturn($file->reveal());

    $result = $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(1, $result->id());
  }

  /**
   * Tests resolving a file from a local file path.
   *
   * Flow: Copies file from local absolute path to destination directory and
   * creates a new file entity. No download takes place. No existing file at
   * destination, no existing file entity.
   *
   * @covers ::resolve
   * @covers ::isPath
   * @covers ::resolvePath
   */
  public function testResolveWithPath() {
    $resolver = $this->createFileResolver();

    // Use a temporary file with absolute path (not a stream wrapper).
    $filename = 'feeds_test_' . uniqid() . '.txt';
    $temp_file = sys_get_temp_dir() . '/' . $filename;
    $content = 'test file content';
    file_put_contents($temp_file, $content);

    // Mock realpath for the destination (stream wrapper).
    $this->fileSystem->realpath('public://files/' . basename($temp_file))
      ->willReturn('/path/to/public/files/' . basename($temp_file));

    // Mock that destination doesn't exist yet.
    // We need to ensure file_exists() on the real path works.
    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(2);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    $this->fileRepository->writeData($content, 'public://files/' . $filename, FileExists::Replace)
      ->willReturn($file->reveal());

    $result = $resolver->resolve($temp_file, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    unlink($temp_file);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(2, $result->id());
  }

  /**
   * Tests resolving a file by numeric file ID.
   *
   * Flow: Loads existing file entity directly by ID. No download takes place.
   * No file operations at destination. File entity already exists.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFileId() {
    $resolver = $this->createFileResolver();

    $file = $this->prophesize(FileInterface::class);
    $file->getFileUri()->willReturn(NULL);
    $file->id()->willReturn(3);
    $this->fileStorage->load(3)
      ->willReturn($file->reveal());

    $result = $resolver->resolve(3, [
      'fields' => ['fid'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(3, $result->id());
  }

  /**
   * Tests resolving existing file ID with a disallowed extension.
   *
   * Flow: Resolves an already existing file entity by ID and validates that
   * extension restrictions are still applied for this lookup path.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   * @covers ::validateFileExtension
   */
  public function testResolveWithFileIdWithDisallowedExtension() {
    $resolver = $this->createFileResolver();

    $file = $this->prophesize(FileInterface::class);
    $file->getFileUri()->willReturn('public://files/existing.exe');
    $this->fileStorage->load(3)
      ->willReturn($file->reveal());

    $this->expectException(InvalidFileExtensionException::class);
    $this->expectExceptionMessage('The file extension "exe" is not allowed');

    $resolver->resolve(3, [
      'fields' => ['fid'],
      'file_extensions' => ['jpg', 'png'],
    ]);
  }

  /**
   * Tests resolving a file by numeric file ID that is not found.
   *
   * Flow: Tries to load an existing file entity directly by ID, but there is no
   * such file. No download takes place.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFileIdFailure() {
    $resolver = $this->createFileResolver();

    $this->fileStorage->load(4)
      ->willReturn(FALSE);

    // Since there only need to be searched by ID, there is no need to consult
    // entity finder.
    $this->entityFinder->findEntities(Argument::any(), Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    // No HTTP request should happen.
    $this->client->request(Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $result = $resolver->resolve(4, [
      'fields' => ['fid'],
    ]);

    $this->assertNull($result);
  }

  /**
   * Tests resolving a file with an illegal value for file ID.
   *
   * Flow: Tries to load an existing file entity directly by ID, but there is no
   * such file. No download takes place.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFileIdFailureNonNumericValue() {
    $resolver = $this->createFileResolver();

    // The file storage should not be consulted because the input value is
    // not numeric.
    $this->fileStorage->load(Argument::any())
      ->shouldNotBeCalled();

    // Since there only need to be searched by ID, there is no need to consult
    // entity finder.
    $this->entityFinder->findEntities(Argument::any(), Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    // No HTTP request should happen.
    $this->client->request(Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $result = $resolver->resolve('yeah', [
      'fields' => ['fid'],
    ]);

    $this->assertNull($result);
  }

  /**
   * Tests resolving a file by filename using entity finder.
   *
   * Flow: Searches for existing file entity by filename field, then loads it.
   * No download takes place. No file operations at destination. File entity
   * already exists.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFilename() {
    $resolver = $this->createFileResolver();

    $this->entityFinder->findEntities('file', 'filename', 'test.txt')
      ->willReturn([5]);

    $file = $this->prophesize(FileInterface::class);
    $file->getFileUri()->willReturn('public://files/test.txt');
    $file->id()->willReturn(5);
    $this->fileStorage->load(5)
      ->willReturn($file->reveal());

    $result = $resolver->resolve('test.txt', [
      'fields' => ['filename'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(5, $result->id());
  }

  /**
   * Tests resolving with empty input.
   *
   * Flow: Returns NULL immediately for empty input. No download, no file
   * operations, no entity lookup.
   *
   * @covers ::resolve
   */
  public function testResolveWithEmptyInput() {
    $resolver = $this->createFileResolver();

    $result = $resolver->resolve('', []);

    $this->assertNull($result);
  }

  /**
   * Tests resolving a URL that fails to download (HTTP 404).
   *
   * Flow: Attempts to download file from URL, but receives HTTP 404 response.
   * Throws DownloadException. No file saved, no file entity created.
   *
   * @covers ::resolve
   * @covers ::resolveUrl
   * @covers ::downloadFile
   */
  public function testResolveUrlWithDownloadFailure() {
    $resolver = $this->createFileResolver();

    // Mock a failed download (HTTP 404).
    $response = new Response(404);
    $this->client->request('GET', 'https://example.com/missing.txt')
      ->willReturn($response);

    // Mock realpath to return NULL so file_exists() is called on the stream
    // wrapper URI. Since the file doesn't exist, file_exists() should return
    // false. We need to ensure the destination path check doesn't fail.
    // Mock realpath for the specific destination path to return NULL.
    $this->fileSystem->realpath('public://files/missing.txt')
      ->willReturn(NULL);
    // For other calls, also return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/missing.txt';
    }))
      ->willReturn(NULL);

    // Expect DownloadException to be thrown.
    $this->expectException(DownloadException::class);
    $this->expectExceptionMessage('Download of https://example.com/missing.txt failed with code 404.');

    try {
      $resolver->resolve('https://example.com/missing.txt', [
        'directory' => 'public://files',
        'existing' => FileExists::Replace,
      ]);
    }
    catch (DownloadException $e) {
      // Verify exception properties.
      $this->assertEquals('https://example.com/missing.txt', $e->getUrl());
      $this->assertEquals(404, $e->getStatusCode());
      $this->assertNull($e->getErrorMessage());
      throw $e;
    }
  }

  /**
   * Tests resolving a URL that fails with a network error.
   *
   * Flow: Attempts to download file from URL, but network request throws
   * RequestException (e.g., connection timeout). Throws DownloadException with
   * error message. No file saved, no file entity created.
   *
   * @covers ::resolve
   * @covers ::resolveUrl
   * @covers ::downloadFile
   */
  public function testResolveUrlWithRequestException() {
    $resolver = $this->createFileResolver();

    // Mock a RequestException (e.g., network error).
    $exception = new RequestException('Connection timeout', $this->prophesize(RequestInterface::class)->reveal());
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willThrow($exception);

    // Mock realpath to return NULL so file_exists() is called on the stream
    // wrapper URI. Since the file doesn't exist, file_exists() should return
    // false. We need to ensure the destination path check doesn't fail.
    // Mock realpath for the specific destination path to return NULL.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn(NULL);
    // For other calls, also return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    // Expect DownloadException to be thrown.
    $this->expectException(DownloadException::class);
    $this->expectExceptionMessage('Download of https://example.com/file.txt failed: Connection timeout');

    try {
      $resolver->resolve('https://example.com/file.txt', [
        'directory' => 'public://files',
        'existing' => FileExists::Replace,
      ]);
    }
    catch (DownloadException $e) {
      // Verify exception properties.
      $this->assertEquals('https://example.com/file.txt', $e->getUrl());
      $this->assertNull($e->getStatusCode());
      $this->assertEquals('Connection timeout', $e->getErrorMessage());
      throw $e;
    }
  }

  /**
   * Tests validation when directory option is missing for URL input.
   *
   * Flow: Validates required options before processing. Throws
   * InvalidArgumentException when 'directory' is missing. No download takes
   * place.
   *
   * @covers ::resolve
   * @covers ::validateUrlPathOptions
   */
  public function testResolveUrlWithMissingDirectory() {
    $resolver = $this->createFileResolver();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "directory" option is required');

    $resolver->resolve('https://example.com/file.txt', [
      'existing' => FileExists::Replace,
    ]);
  }

  /**
   * Tests validation when existing option is missing for URL input.
   *
   * Flow: Validates required options before processing. Throws
   * InvalidArgumentException when 'existing' is missing. No download takes
   * place.
   *
   * @covers ::resolve
   * @covers ::validateUrlPathOptions
   */
  public function testResolveUrlWithMissingExisting() {
    $resolver = $this->createFileResolver();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "existing" option is required');

    $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
    ]);
  }

  /**
   * Tests validation when fields option is missing for other value input.
   *
   * Flow: Validates required options before processing. Throws
   * InvalidArgumentException when 'fields' is missing. No entity lookup takes
   * place.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithMissingFields() {
    $resolver = $this->createFileResolver();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The "fields" option is required');

    $resolver->resolve('some-value', ['fields' => []]);
  }

  /**
   * Tests resolving a URL when file repository service is not available.
   *
   * Flow: Validates that file repository service exists before processing URL.
   * Throws \RuntimeException when service is NULL. No download takes place.
   *
   * @covers ::resolve
   * @covers ::resolveUrl
   */
  public function testResolveUrlWithoutFileRepository() {
    $resolver = new FileResolver(
      $this->client->reveal(),
      $this->entityTypeManager->reveal(),
      $this->entityFinder->reveal(),
      $this->fileSystem->reveal(),
      $this->streamWrapperManager->reveal(),
      $this->currentUser->reveal(),
      NULL
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The file.repository service is not available');

    $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);
  }

  /**
   * Tests resolving a URL with FileExists enum when file exists.
   *
   * Flow: Downloads file from URL. File already exists at destination with
   * different content (hash comparison fails). Uses FileExists::Rename to save
   * with new name. Creates new file entity.
   *
   * @covers ::normalizeFileExists
   * @covers ::resolveUrl
   */
  public function testNormalizeFileExistsWithEnum() {
    $resolver = $this->createFileResolver();

    $content = 'test file content';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willReturn($response);

    // Create a temporary file with DIFFERENT content so hash comparison fails
    // and handleDifferentContent() is called with the existing setting.
    $temp_dir = sys_get_temp_dir();
    $temp_path = $temp_dir . '/public_files_file_enum.txt';
    // Write different content so hash comparison will fail.
    file_put_contents($temp_path, 'different content');

    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn($temp_path);
    // For other calls, return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);
    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(1);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    // When file exists with different content, handleDifferentContent() is
    // called which uses the 'existing' setting (FileExists::Rename in this
    // case).
    $this->fileRepository->writeData($content, 'public://files/file.txt', FileExists::Rename)
      ->willReturn($file->reveal());

    $result = $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Rename,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(1, $result->id());

    // Cleanup.
    if (file_exists($temp_path)) {
      unlink($temp_path);
    }
  }

  /**
   * Tests resolving a URL with legacy integer constant for existing files.
   *
   * Flow: Downloads file from URL. File already exists at destination. Uses
   * legacy integer constant (1 = Replace) which is normalized to
   * FileExists::Replace.
   * Replaces existing file. Download takes place, file exists at destination,
   * new file entity created (replacing existing).
   *
   * @covers ::normalizeFileExists
   * @covers ::resolveUrl
   */
  public function testNormalizeFileExistsWithLegacyInt() {
    $resolver = $this->createFileResolver();

    $content = 'test';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willReturn($response);

    // Create a temporary file that actually exists so file_exists() works.
    $temp_dir = sys_get_temp_dir();
    $temp_file = $temp_dir . '/public_files_file.txt';
    // Ensure the file doesn't exist initially.
    if (file_exists($temp_file)) {
      unlink($temp_file);
    }

    // Mock realpath to return the temp file path so file_exists() works.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn($temp_file);
    // For other calls, return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(1);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    $this->fileRepository->writeData(Argument::any(), Argument::any(), FileExists::Replace)
      ->willReturn($file->reveal());

    // Test with legacy integer constant (1 = EXISTS_REPLACE).
    $result = $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => 1,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);

    // Cleanup.
    if (file_exists($temp_file)) {
      unlink($temp_file);
    }
  }

  /**
   * Tests resolving a URL with string value for existing file handling.
   *
   * Flow: Downloads file from URL. File already exists at destination with
   * different content (hash comparison fails). Uses string value 'rename' which
   * is normalized to FileExists::Rename. Saves with new name. Download takes
   * place, file exists at destination with different content, new file entity
   * created.
   *
   * @covers ::normalizeFileExists
   * @covers ::resolveUrl
   */
  public function testNormalizeFileExistsWithString() {
    $resolver = $this->createFileResolver();

    $content = 'test';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willReturn($response);

    // Create a temporary file with DIFFERENT content so hash comparison fails
    // and handleDifferentContent() is called with the existing setting.
    $temp_dir = sys_get_temp_dir();
    $temp_file = $temp_dir . '/public_files_file_rename.txt';
    // Write different content so hash comparison will fail.
    file_put_contents($temp_file, 'different content');

    // Mock realpath to return the temp file path so file_exists() works.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn($temp_file);
    // For other calls, return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(1);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    // When file exists with different content, handleDifferentContent() is
    // called which uses the 'existing' setting (FileExists::Rename in this
    // case).
    $this->fileRepository->writeData(Argument::any(), Argument::any(), FileExists::Rename)
      ->willReturn($file->reveal());

    // Test with string name.
    $result = $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => 'rename',
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);

    // Cleanup.
    if (file_exists($temp_file)) {
      unlink($temp_file);
    }
  }

  /**
   * Tests resolving a URL with allowed file extension.
   *
   * Flow: Downloads file from URL. Validates file extension against allowed
   * list (txt is allowed). Saves file to destination and creates new file
   * entity. Download takes place, no existing file at destination, new file
   * entity created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolveUrl
   */
  public function testResolveWithUrlWithAllowedExtension() {
    $resolver = $this->createFileResolver();

    $content = 'test file content';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.txt')
      ->willReturn($response);

    // Mock realpath to return a real path so file_exists() works.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn('/tmp/public/files/file.txt');
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(1);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    $this->fileRepository->writeData($content, 'public://files/file.txt', FileExists::Replace)
      ->willReturn($file->reveal());

    // Test with allowed extension.
    $result = $resolver->resolve('https://example.com/file.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['txt', 'pdf'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(1, $result->id());
  }

  /**
   * Tests resolving a URL with disallowed file extension.
   *
   * Flow: Attempts to download file from URL. Validates file extension against
   * allowed list ('exe' is not allowed). Throws InvalidFileExtensionException
   * before saving. Download takes place, but file is not saved, no file entity
   * created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolveUrl
   */
  public function testResolveWithUrlWithDisallowedExtension() {
    $resolver = $this->createFileResolver();

    $content = 'test file content';
    $response = new Response(200, [], $content);
    $this->client->request('GET', 'https://example.com/file.exe')
      ->willReturn($response);

    // Mock realpath to return a real path so file_exists() works.
    $this->fileSystem->realpath(Argument::any())
      ->willReturn(NULL);

    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    // Test with disallowed extension.
    $this->expectException(InvalidFileExtensionException::class);
    $this->expectExceptionMessage('The file extension "exe" is not allowed');

    $resolver->resolve('https://example.com/file.exe', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['txt', 'pdf'],
    ]);
  }

  /**
   * Tests resolving a local path with allowed file extension.
   *
   * Flow: Copies file from local absolute path. Validates file extension
   * against allowed list (pdf is allowed). Saves file to destination and
   * creates new file entity. No download takes place, no existing file at
   * destination, new file entity created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolvePath
   */
  public function testResolveWithPathWithAllowedExtension() {
    $resolver = $this->createFileResolver();

    // Use a temporary file with absolute path (not a stream wrapper).
    $filename = 'feeds_test_' . uniqid() . '.pdf';
    $temp_file = sys_get_temp_dir() . '/' . $filename;
    $content = 'test file content';
    file_put_contents($temp_file, $content);

    // Mock realpath for the destination (stream wrapper).
    $this->fileSystem->realpath('public://files/' . $filename)
      ->willReturn('/path/to/public/files/' . $filename);

    // Mock that destination doesn't exist yet.
    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(2);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(1)->shouldBeCalled();
    $file->save()->shouldBeCalled();
    $this->fileRepository->writeData($content, 'public://files/' . $filename, FileExists::Replace)
      ->willReturn($file->reveal());

    // Test with allowed extension.
    $result = $resolver->resolve($temp_file, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['txt', 'pdf'],
    ]);

    unlink($temp_file);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(2, $result->id());
  }

  /**
   * Tests resolving a local path with disallowed file extension.
   *
   * Flow: Attempts to copy file from local absolute path. Validates file
   * extension against allowed list ('exe' is not allowed). Throws
   * InvalidFileExtensionException before saving. No download takes place, file
   * is not saved, no file entity created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolvePath
   */
  public function testResolveWithPathWithDisallowedExtension() {
    $resolver = $this->createFileResolver();

    // Use a temporary file with absolute path (not a stream wrapper).
    $filename = 'feeds_test_' . uniqid() . '.exe';
    $temp_file = sys_get_temp_dir() . '/' . $filename;
    $content = 'test file content';
    file_put_contents($temp_file, $content);

    // Mock realpath for the destination (stream wrapper).
    $this->fileSystem->realpath('public://files/' . $filename)
      ->willReturn('/path/to/public/files/' . $filename);

    // Mock that destination doesn't exist yet.
    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    // Test with disallowed extension.
    $this->expectException(InvalidFileExtensionException::class);
    $this->expectExceptionMessage('The file extension "exe" is not allowed');

    try {
      $resolver->resolve($temp_file, [
        'directory' => 'public://files',
        'existing' => FileExists::Replace,
        'file_extensions' => ['txt', 'pdf'],
      ]);
    }
    finally {
      unlink($temp_file);
    }
  }

  /**
   * Tests find an existing file by field lookup with a url value.
   *
   * Flow: Input is a URL, but 'fields' option is provided. First checks if an
   * existing file entity has this URL value in the specified field. If found,
   * returns the existing entity without downloading. No download takes place,
   * existing file entity is returned.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveUrlWithExistingFileEntityByField() {
    $resolver = $this->createFileResolver();

    // Mock entity finder to find an existing file entity by searching in
    // field_alpha for the URL value.
    $this->entityFinder->findEntities('file', 'field_alpha', 'https://www.example.com/files/file.txt')
      ->willReturn([5]);

    $file = $this->prophesize(FileInterface::class);
    $file->getFileUri()->willReturn('public://files/file.txt');
    $file->id()->willReturn(5);
    $this->fileStorage->load(5)
      ->willReturn($file->reveal());

    // Verify that no HTTP request is made (no download should occur).
    // FileResolver should check fields option first before attempting download.
    $this->client->request(Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $result = $resolver->resolve('https://www.example.com/files/file.txt', [
      'directory' => 'public://files',
      'existing' => 'rename',
      'fields' => ['field_alpha'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(5, $result->id());
  }

  /**
   * Tests resolving a URL with 'existing' set to 'ignore' when file exists.
   *
   * Flow: Input is a URL that would normally download to
   * 'public://files/file.txt'. With 'existing' set to 'ignore', if a physical
   * file already exists at that location, the download can be skipped. File
   * repository's loadByUri() method is used to find the existing file entity by
   * URI. Returns the existing entity without downloading. No download takes
   * place, existing file entity is returned. This optimization only applies
   * when 'existing' is 'ignore'.
   *
   * @covers ::resolve
   * @covers ::resolveUrl
   */
  public function testResolveUrlWithExistingIgnoreSkipsDownload() {
    $resolver = $this->createFileResolver();

    // Create a temporary file that represents the existing physical file.
    $temp_dir = sys_get_temp_dir();
    $temp_path = $temp_dir . '/public_files_file_existing.txt';
    file_put_contents($temp_path, 'existing file content');

    // Mock realpath to return the temp file path so file_exists() works.
    $this->fileSystem->realpath('public://files/file.txt')
      ->willReturn($temp_path);
    // For other calls, return NULL.
    $this->fileSystem->realpath(Argument::that(function ($arg) {
      return $arg !== 'public://files/file.txt';
    }))
      ->willReturn(NULL);

    // Mock entity finder: It will be called with 'filename' field (from
    // fields option check). This should return empty (file not found by
    // filename).
    $this->entityFinder->findEntities('file', 'filename', 'https://www.example.com/files/file.txt')
      ->willReturn([]);
    // Mock file repository: loadByUri() will be called to find the file by URI.
    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(6);
    $this->fileRepository->loadByUri('public://files/file.txt')
      ->willReturn($file->reveal());

    // Verify that no HTTP request is made (no download should occur).
    // With 'existing' set to 'ignore', FileResolver should check for existing
    // file entity by URI before downloading.
    $this->client->request(Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    // Verify that writeData() is not called since we're returning an existing
    // file.
    $this->fileRepository->writeData(Argument::any(), Argument::any(), Argument::any())
      ->shouldNotBeCalled();

    $result = $resolver->resolve('https://www.example.com/files/file.txt', [
      'directory' => 'public://files',
      'existing' => 'ignore',
      'fields' => ['filename'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(6, $result->id());

    // Cleanup.
    if (file_exists($temp_path)) {
      unlink($temp_path);
    }
  }

  /**
   * Tests getting file extension from URL.
   *
   * @covers ::getFileExtension
   */
  public function testGetFileExtensionFromUrl() {
    $resolver = $this->createFileResolver();

    $this->assertEquals('jpg', $resolver->getFileExtension('https://example.com/image.jpg'));
    $this->assertEquals('pdf', $resolver->getFileExtension('https://example.com/document.pdf'));
    $this->assertEquals('png', $resolver->getFileExtension('http://example.com/image.PNG'));
    $this->assertEquals('txt', $resolver->getFileExtension('https://example.com/file.txt?param=value'));
    $this->assertEquals('', $resolver->getFileExtension('https://example.com/file'));
    $this->assertEquals('', $resolver->getFileExtension('https://example.com/'));
    $this->assertEquals('', $resolver->getFileExtension('https://example.com/.well-known'));
    $this->assertEquals('', $resolver->getFileExtension('https://example.com/.env?download=1'));
  }

  /**
   * Tests getting file extension from file path.
   *
   * @covers ::getFileExtension
   */
  public function testGetFileExtensionFromPath() {
    $resolver = $this->createFileResolver();

    $this->assertEquals('txt', $resolver->getFileExtension('/var/www/files/document.txt'));
    $this->assertEquals('pdf', $resolver->getFileExtension('public://files/document.pdf'));
    $this->assertEquals('jpg', $resolver->getFileExtension('/path/to/image.JPG'));
    $this->assertEquals('', $resolver->getFileExtension('/var/www/files/document'));
    $this->assertEquals('', $resolver->getFileExtension('/var/www/files/'));
    $this->assertEquals('', $resolver->getFileExtension('/var/www/files/.htaccess'));
    $this->assertEquals('', $resolver->getFileExtension('/var/www/files/.env'));
  }

  /**
   * Tests getting file extension from FileInterface.
   *
   * @covers ::getFileExtension
   */
  public function testGetFileExtensionFromFile() {
    $resolver = $this->createFileResolver();

    $file = $this->prophesize(FileInterface::class);
    $file->getFileUri()->willReturn('public://files/image.jpg');
    $hidden_file = $this->prophesize(FileInterface::class);
    $hidden_file->getFileUri()->willReturn('public://files/.htaccess');

    $this->assertEquals('jpg', $resolver->getFileExtension($file->reveal()));
    $this->assertEquals('', $resolver->getFileExtension($hidden_file->reveal()));
  }

  /**
   * Tests resolving with owner_id option.
   *
   * Flow: Resolves a file path with owner_id option provided. The file entity
   * should be created with the specified owner ID instead of current user ID.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::saveFileAndCreateEntity
   */
  public function testResolveWithOwnerId() {
    $resolver = $this->createFileResolver();

    // Mock file entity that will be created.
    $file = $this->prophesize(FileInterface::class);
    $file->id()->willReturn(10);
    $file->getOwnerId()->willReturn(0);
    $file->setOwnerId(5)->shouldBeCalled();
    $file->save()->shouldBeCalled();

    // Mock file repository to return the file when writeData is called.
    $this->fileRepository->writeData(Argument::any(), Argument::any(), Argument::any())
      ->willReturn($file->reveal());

    // Mock file system for directory operations.
    $this->fileSystem->prepareDirectory(Argument::any(), Argument::any())
      ->willReturn(TRUE);

    // Create a temporary file for the source.
    $temp_file = tempnam(sys_get_temp_dir(), 'feeds_test_');
    file_put_contents($temp_file, 'test content');

    // Mock realpath to return different paths for source and destination
    // to ensure we go through saveFileAndCreateEntity() instead of
    // findOrCreateFileEntity().
    $this->fileSystem->realpath($temp_file)
      ->willReturn($temp_file);
    $this->fileSystem->realpath('public://files/' . basename($temp_file))
      ->willReturn('/different/path/' . basename($temp_file));

    $result = $resolver->resolve($temp_file, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => 5,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals(10, $result->id());

    // Cleanup.
    unlink($temp_file);
  }

}
