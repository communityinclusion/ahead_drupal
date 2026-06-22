<?php

namespace Drupal\Tests\feeds\Kernel\Utility;

use Drupal\feeds\Utility\FileResolver;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\feeds\Exception\FileNotFoundException;
use Drupal\feeds\Exception\InvalidFileExtensionException;

/**
 * @coversDefaultClass \Drupal\feeds\Utility\FileResolver
 * @group feeds
 */
class FileResolverTest extends FeedsKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field',
    'node',
    'feeds',
    'text',
    'filter',
    'options',
    'file',
  ];

  /**
   * The file resolver service.
   *
   * @var \Drupal\feeds\Utility\FileResolverInterface
   */
  protected $fileResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Create necessary directories.
    $file_system = $this->container->get('file_system');
    $files_dir = 'public://files';
    $source_dir = 'public://source';
    $file_system->prepareDirectory($files_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file_system->prepareDirectory($source_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $this->fileResolver = $this->container->get('feeds.file_resolver');
  }

  /**
   * Returns the absolute path to the public file directory.
   *
   * The returned path does not end with a trailing slash.
   *
   * @return string
   *   The absolute path to the public file directory.
   */
  protected function getAbsolutePublicDirectoryPath(): string {
    $path = $this->container->get('file_system')->realpath('public://');
    return rtrim($path, '/');
  }

  /**
   * Tests resolving a file by numeric file ID.
   *
   * Flow: Loads existing file entity directly by ID using entity storage.
   * No file operations, no download. File entity already exists.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFileId() {
    // Create a test file.
    $file = $this->writeData('test content', 'public://test.txt', FileExists::Replace);

    // Resolve by file ID.
    $result = $this->fileResolver->resolve($file->id(), [
      'fields' => ['fid'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($file->id(), $result->id());
    $this->assertEquals($file->getFileUri(), $result->getFileUri());
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
    $result = $this->fileResolver->resolve(8, [
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
    $result = $this->fileResolver->resolve('yeah', [
      'fields' => ['fid'],
    ]);

    $this->assertNull($result);
  }

  /**
   * Tests resolving a file by filename using entity finder.
   *
   * Flow: Searches for existing file entity by filename field using entity
   * finder, then loads it. No file operations, no download. File entity
   * already exists.
   *
   * @covers ::resolve
   * @covers ::resolveByFields
   */
  public function testResolveWithFilename() {
    // Create a test file.
    $file = $this->writeData('test content', 'public://testfile.txt', FileExists::Replace);

    // Resolve by filename.
    $result = $this->fileResolver->resolve('testfile.txt', [
      'fields' => ['filename'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($file->id(), $result->id());
  }

  /**
   * Tests resolving a file by stream wrapper URI.
   *
   * Flow: Input is a stream wrapper URI (public://). Copies file from source
   * URI to destination directory and creates file entity. No download takes
   * place. No existing file at destination, new file entity created.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithUri() {
    // Create a test file at an other location than the destination.
    $file = $this->writeData('test content', 'public://test-uri.txt', FileExists::Replace);

    // Resolve by URI.
    $result = $this->fileResolver->resolve('public://test-uri.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Rename,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // The file should be found or created in the destination directory.
    $this->assertStringContainsString('files/test-uri.txt', $result->getFileUri());
    // Should be a new file entity.
    $this->assertNotEquals($file->id(), $result->id());
  }

  /**
   * Tests resolving a file from a local absolute file path.
   *
   * Flow: Copies file from local absolute path to destination directory and
   * creates new file entity. No download takes place. No existing file at
   * destination, new file entity created.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPath() {
    // Create a source file.
    $source_file = $this->writeData('source content', 'public://source.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Resolve the path to a file entity in a different directory.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertStringContainsString('files/source.txt', $result->getFileUri());
    // Should be a new file entity.
    $this->assertNotEquals($source_file->id(), $result->id());
  }

  /**
   * Tests resolving a file path when source and destination are the same.
   *
   * Flow: Source file path and destination directory point to the same
   * location. Detects that files are the same and returns existing file
   * entity without copying. No download takes place, existing file
   * entity returned.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathSameLocation() {
    // Create a source file.
    $source_file = $this->writeData('source content', 'public://files/source.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/files/source.txt';

    // Resolve the path when destination is the same as source.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Rename,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // Should return the existing file entity.
    $this->assertEquals($source_file->id(), $result->id());
    // Assert that no renaming took place.
    $this->assertStringContainsString('files/source.txt', $result->getFileUri());
  }

  /**
   * Tests resolving a file path with 'Replace' option when file exists.
   *
   * Flow: File already exists at destination with different content. With
   * FileExists::Replace, replaces the existing file with new content from
   * source. No download takes place, existing file entity updated with new
   * content.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathExistingReplace() {
    // Create existing file with different content.
    $existing_file = $this->writeData('old content', 'public://files/test.txt', FileExists::Replace);

    // Create source file with new content.
    $source_file = $this->writeData('new content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Resolve with 'Replace' option.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // Should have replaced the file.
    $this->assertEquals('new content', file_get_contents($this->container->get('file_system')->realpath('public://files/test.txt')));
  }

  /**
   * Tests resolving a file path with 'Rename' option when file exists.
   *
   * Flow: File already exists at destination with different content. With
   * FileExists::Rename, creates new file with incremented name (e.g.,
   * test_0.txt). No download takes place, new file entity created with
   * renamed file.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathExistingRename() {
    // Create existing file with different content.
    $existing_file = $this->writeData('old content', 'public://files/test.txt', FileExists::Replace);

    // Create source file with new content.
    $source_file = $this->writeData('new content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Resolve with 'Rename' option.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Rename,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // Should have created a new file with a different name.
    $this->assertNotEquals($existing_file->id(), $result->id());
    $this->assertStringContainsString('files/test_0.txt', $result->getFileUri());
  }

  /**
   * Tests resolving a file path with Error (Ignore) option when file exists.
   *
   * Flow: File already exists at destination with different content. With
   * FileExists::Error (Ignore), returns existing file entity without
   * modifying it. No download takes place, existing file entity returned
   * unchanged.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathExistingIgnore() {
    // Create existing file with different content.
    $existing_file = $this->writeData('old content', 'public://files/test.txt', FileExists::Replace);

    // Create source file with new content.
    $source_file = $this->writeData('new content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Resolve with Error (Ignore) option.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Error,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // Should return the existing file entity, not create a new one.
    $this->assertEquals($existing_file->id(), $result->id());
    // Content should still be old.
    $this->assertEquals('old content', file_get_contents($this->container->get('file_system')->realpath('public://files/test.txt')));
  }

  /**
   * Tests that file comparison is skipped when existing is 'ignore'.
   *
   * Flow: File already exists at destination. With FileExists::Error
   * (Ignore), the optimization should skip hash_file() comparison and
   * file_get_contents() reading since no file will be written anyway. The
   * existing file entity is returned immediately.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathExistingIgnoreSkipsComparison() {
    // Create existing file at destination.
    $existing_file = $this->writeData('existing content', 'public://files/test.txt', FileExists::Replace);
    $existing_file_path = $this->getAbsolutePublicDirectoryPath() . '/files/test.txt';

    // Create source file with different content.
    $source_file = $this->writeData('source content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure both files exist.
    $this->assertTrue(file_exists($source_path), 'Source file should exist');
    $this->assertTrue(file_exists($existing_file_path), 'Existing file should exist');

    // Record the file modification time before resolution to verify no writes.
    $existing_mtime_before = filemtime($existing_file_path);

    // Resolve with Error (Ignore) option. With the optimization, this should
    // skip hash_file() comparison and file_get_contents() reading.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Error,
    ]);

    // Verify the existing file entity is returned.
    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($existing_file->id(), $result->id());

    // Verify the file content hasn't changed (proves no write occurred).
    $this->assertEquals('existing content', file_get_contents($existing_file_path));

    // Verify the file modification time hasn't changed (proves no write
    // occurred).
    $existing_mtime_after = filemtime($existing_file_path);
    $this->assertEquals($existing_mtime_before, $existing_mtime_after, 'File modification time should not change when existing is ignore');

    // Verify the source file content is different (to ensure we would have
    // detected a difference if comparison had occurred).
    $this->assertNotEquals('existing content', file_get_contents($source_path), 'Source file should have different content');
  }

  /**
   * Tests resolving a file path when destination file has same content.
   *
   * Flow: File already exists at destination with identical content (hash
   * comparison matches). Returns existing file entity without copying. No
   * download takes place, existing file entity returned.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithPathSameContent() {
    // Create existing file.
    $existing_file = $this->writeData('same content', 'public://files/test.txt', FileExists::Replace);

    // Create source file with same content.
    $source_file = $this->writeData('same content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Resolve the path.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Rename,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    // Should return the existing file entity since content is the same.
    $this->assertEquals($existing_file->id(), $result->id());
  }

  /**
   * Tests resolving with legacy integer constant for existing file handling.
   *
   * Flow: Copies file from local path to destination. Uses legacy integer
   * constant (1 = Replace) which is normalized to FileExists::Replace. No
   * download takes place, new file entity created.
   *
   * @covers ::resolve
   * @covers ::normalizeFileExists
   * @covers ::resolvePath
   */
  public function testResolveWithLegacyIntConstant() {
    // Create a source file.
    $source_file = $this->writeData('test', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Test with legacy integer constant (1 = EXISTS_REPLACE).
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => 1,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
  }

  /**
   * Tests resolving with string value for existing file handling.
   *
   * Flow: Copies file from local path to destination. Uses string value
   * 'rename' which is normalized to FileExists::Rename. No download takes
   * place, new file entity created.
   *
   * @covers ::resolve
   * @covers ::normalizeFileExists
   * @covers ::resolvePath
   */
  public function testResolveWithStringName() {
    // Create a source file.
    $source_file = $this->writeData('test', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Test with string name.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => 'rename',
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
  }

  /**
   * Tests resolving with empty input.
   *
   * Flow: Returns NULL immediately for empty input. No file operations, no
   * download, no entity lookup.
   *
   * @covers ::resolve
   */
  public function testResolveWithEmptyInput() {
    $result = $this->fileResolver->resolve('', []);

    $this->assertNull($result);
  }

  /**
   * Tests resolving with non-existent file path.
   *
   * Flow: Input is a path that does not exist on the file system. A
   * FileNotFoundException is expected since source file cannot be found. No
   * download takes place, no file entity created.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithInvalidPath() {
    // Expect FileNotFoundException to be thrown.
    $this->expectException(FileNotFoundException::class);
    $this->expectExceptionMessage("The file /nonexistent/path/file.txt does not exist.");

    $result = $this->fileResolver->resolve('/nonexistent/path/file.txt', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);
  }

  /**
   * Tests resolving with non-existent absolute path, no file at destination.
   *
   * Flow: Input is an absolute path to a file that does not exist. No file
   * exists at the destination directory. A FileNotFoundException is expected
   * since source file cannot be found. No file operations take place.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithNonExistentAbsolutePathNoDestinationFile() {
    $non_existent_path = $this->getAbsolutePublicDirectoryPath() . '/nonexistent/file.txt';

    // Ensure the file doesn't exist.
    $this->assertFalse(file_exists($non_existent_path), 'Source file should not exist');

    // Ensure no file exists at destination with the same name.
    $destination_path = $this->getAbsolutePublicDirectoryPath() . '/files/file.txt';
    $this->assertFalse(file_exists($destination_path), 'Destination file should not exist');

    // Expect FileNotFoundException to be thrown.
    $this->expectException(FileNotFoundException::class);
    $this->expectExceptionMessage("The file $non_existent_path does not exist.");

    $result = $this->fileResolver->resolve($non_existent_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);
  }

  /**
   * Tests resolving with non-existent path, file exists at destination.
   *
   * Flow: Input is an absolute path to a file that does not exist. A file with
   * the same filename exists at the destination. A FileNotFoundException is
   * expected since source file cannot be found. The fact that there already is
   * an existing file at the destination does not matter here. No file
   * operations take place.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   */
  public function testResolveWithNonExistentAbsolutePathWithDestinationFile() {
    // Create a file at the destination with the same name as the non-existent
    // source.
    $existing_file = $this->writeData('existing content', 'public://files/file.txt', FileExists::Replace);
    $destination_path = $this->getAbsolutePublicDirectoryPath() . '/files/file.txt';

    // Ensure the destination file exists.
    $this->assertTrue(file_exists($destination_path), 'Destination file should exist');

    // Use a non-existent source path with the same filename.
    $non_existent_path = $this->getAbsolutePublicDirectoryPath() . '/nonexistent/file.txt';

    // Ensure the source file doesn't exist.
    $this->assertFalse(file_exists($non_existent_path), 'Source file should not exist');

    // Expect FileNotFoundException to be thrown.
    $this->expectException(FileNotFoundException::class);
    $this->expectExceptionMessage("The file $non_existent_path does not exist.");

    try {
      $result = $this->fileResolver->resolve($non_existent_path, [
        'directory' => 'public://files',
        'existing' => FileExists::Replace,
      ]);
    }
    catch (FileNotFoundException $e) {
      // Verify the destination file is unchanged.
      $this->assertTrue(file_exists($destination_path), 'Destination file should still exist');
      $this->assertEquals('existing content', file_get_contents($destination_path), 'Destination file content should be unchanged');

      throw $e;
    }
  }

  /**
   * Tests resolving a file path with allowed file extension.
   *
   * Flow: Copies file from local path to destination. Validates file
   * extension against allowed list (pdf is allowed). Saves file and creates
   * new file entity. No download takes place, new file entity created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolvePath
   */
  public function testResolveWithPathWithAllowedExtension() {
    // Create a source file with allowed extension.
    $source_file = $this->writeData('test content', 'public://source/test.pdf', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.pdf';

    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['txt', 'pdf'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertStringContainsString('files/test.pdf', $result->getFileUri());
  }

  /**
   * Tests resolving a file path with disallowed file extension.
   *
   * Flow: Attempts to copy file from local path. Validates file extension
   * against allowed list (exe is not allowed). Throws
   * InvalidFileExtensionException before saving. No download takes place, file
   * is not saved, no file entity created.
   *
   * @covers ::resolve
   * @covers ::validateFileExtension
   * @covers ::resolvePath
   */
  public function testResolveWithPathWithDisallowedExtension() {
    // Create a source file with disallowed extension.
    $source_file = $this->writeData('test content', 'public://source/test.exe', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.exe';

    // Test with disallowed extension.
    $this->expectException(InvalidFileExtensionException::class);
    $this->expectExceptionMessage('The file extension "exe" is not allowed');

    $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['txt', 'pdf'],
    ]);
  }

  /**
   * Tests resolving a URL with uppercase file extension.
   *
   * Flow: Downloads file from URL with uppercase extension (.JPG). Validates
   * file extension case-insensitively against allowed list (jpg is allowed).
   * Saves file to destination and creates new file entity. Download takes
   * place, no existing file at destination, new file entity created.
   *
   * @covers ::resolve
   * @covers ::resolveUrl
   * @covers ::validateFileExtension
   * @covers ::downloadFile
   */
  public function testResolveWithUrlWithUppercaseExtension() {
    // Mock HTTP client to return file content.
    $content = 'test image content';
    $client = $this->getMockBuilder(ClientInterface::class)
      ->getMock();
    $response = $this->getMockBuilder(ResponseInterface::class)
      ->getMock();
    $stream = $this->getMockBuilder(StreamInterface::class)
      ->getMock();

    $response->method('getStatusCode')->willReturn(200);
    $response->method('getBody')->willReturn($stream);
    $stream->method('__toString')->willReturn($content);

    $client->expects($this->once())
      ->method('request')
      ->with('GET', 'https://example.com/image.JPG')
      ->willReturn($response);

    // Replace HTTP client in container with mock.
    $this->container->set('http_client', $client);

    // Re-instantiate FileResolver with mocked client.
    $file_resolver = FileResolver::create($this->container);

    // Test resolving URL with uppercase extension.
    // The extension validation should be case-insensitive.
    $result = $file_resolver->resolve('https://example.com/image.JPG', [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'file_extensions' => ['jpg', 'jpeg', 'png'],
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertStringContainsString('files/image.JPG', $result->getFileUri());
  }

  /**
   * Tests that FileResolver handles EntityStorageException gracefully.
   *
   * Flow: Attempts to save a file from local path to destination. File
   * repository throws EntityStorageException when trying to save the file
   * entity (simulating database error, validation failure, etc.).
   * FileResolver catches the exception and returns NULL instead of
   * propagating it. No download takes place, file entity save fails,
   * NULL returned.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::saveFileAndCreateEntity
   */
  public function testResolveHandlesEntityStorageException() {
    // Create a source file.
    $source_file = $this->writeData('test content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Ensure we have a valid path.
    $this->assertTrue(file_exists($source_path), 'Source file should exist at the real path');

    // Create a mock file repository that throws EntityStorageException.
    $mock_file_repository = $this->createMock(FileRepositoryInterface::class);
    $mock_file_repository->expects($this->once())
      ->method('writeData')
      ->willThrowException(new EntityStorageException('Database error: Unable to save file entity'));

    // Temporarily replace the file repository service with our mock.
    $original_file_repository = $this->container->get('file.repository');
    $this->container->set('file.repository', $mock_file_repository);

    // Re-instantiate FileResolver to use the mocked file repository.
    $file_resolver = FileResolver::create($this->container);

    // Attempt to resolve the file path.
    // This should trigger writeData() which will throw EntityStorageException.
    $result = $file_resolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    // FileResolver should catch the exception and return NULL.
    $this->assertNull($result, 'FileResolver should return NULL when EntityStorageException is thrown');

    // Restore the original file repository service.
    $this->container->set('file.repository', $original_file_repository);
  }

  /**
   * Tests creating a new file entity for an existing physical file.
   *
   * Flow: A physical file exists on disk but no file entity exists in the
   * database. When resolve() is called with the same source and destination,
   * findOrCreateFileEntity() is triggered. Since loadByUri() returns NULL, a
   * new file entity is created using File::create(), the owner is set to the
   * current user, and the entity is saved. No download takes place, new file
   * entity created for existing physical file.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::findOrCreateFileEntity
   */
  public function testCreateNewFileEntityForExistingPhysicalFile() {
    $file_system = $this->container->get('file_system');
    $file_repository = $this->container->get('file.repository');
    $current_user = $this->container->get('current_user');

    // Create a physical file directly on disk without creating a file entity.
    $destination_uri = 'public://files/orphan.txt';
    $destination_path = $this->getAbsolutePublicDirectoryPath() . '/files/orphan.txt';

    // Ensure directory exists.
    $directory = 'public://files';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create physical file directly (bypassing file repository).
    file_put_contents($destination_path, 'orphan file content');

    // Verify no file entity exists for this URI.
    $existing_file = $file_repository->loadByUri($destination_uri);
    $this->assertNull($existing_file, 'No file entity should exist for the orphan file');

    // Resolve with source path pointing to the same location as destination.
    // This will trigger findOrCreateFileEntity().
    $result = $this->fileResolver->resolve($destination_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    // Verify a new file entity was created.
    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertNotNull($result->id(), 'File entity should have an ID');

    // Verify the file entity has the correct URI.
    $this->assertEquals($destination_uri, $result->getFileUri());

    // Verify the file entity has the current user as owner.
    $this->assertEquals($current_user->id(), $result->getOwnerId(), 'File entity should have current user as owner');

    // Verify the file is marked as permanent.
    $this->assertTrue($result->isPermanent(), 'File entity should be marked as permanent');

    // Verify the file entity can be loaded by URI now.
    $loaded_file = $file_repository->loadByUri($destination_uri);
    $this->assertInstanceOf(FileInterface::class, $loaded_file);
    $this->assertEquals($result->id(), $loaded_file->id());
  }

  /**
   * Tests getting file extension from FileInterface.
   *
   * @covers ::getFileExtension
   */
  public function testGetFileExtensionFromFile() {
    $file = $this->writeData('test content', 'public://files/image.jpg', FileExists::Replace);
    $this->assertEquals('jpg', $this->fileResolver->getFileExtension($file));
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
    // Create a source file.
    $source_file = $this->writeData('test content', 'public://source/test.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test.txt';

    // Create a test user to use as owner.
    $test_user = $this->createUser();
    $owner_id = $test_user->id();

    // Resolve with owner_id option.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $owner_id,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($owner_id, $result->getOwnerId(), 'File should be owned by the specified owner ID');
  }

  /**
   * Tests that an owner is set for an existing file that has no owner yet.
   *
   * Flow: Resolves a file path with owner_id option provided. The file entity
   * that did not have a owner yet, should now get a owner.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::findOrCreateFileEntity
   */
  public function testSetOwnerForExistingFileThatHasNoOwnerYet() {
    // Create a test user to use as owner.
    $test_user = $this->createUser();
    $owner_id = $test_user->id();

    // Create a source file without an owner.
    $source_file = $this->writeData('test content', 'public://files/test.txt', FileExists::Replace);
    $this->assertEquals(0, $source_file->getOwnerId());
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/files/test.txt';

    // Resolve with owner_id option. Note that the destination of the file is
    // the same as on the source, so no file copy should take place.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $owner_id,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($owner_id, $result->getOwnerId(), 'File should be owned by the specified owner ID');
  }

  /**
   * Tests saveFileAndCreateEntity() sets owner for existing ownerless file.
   *
   * Flow: A file entity already exists at destination and has owner ID 0.
   * Resolving a different source file with existing=Replace triggers
   * saveFileAndCreateEntity(), which should set owner_id on the existing file
   * entity if it does not have an owner yet.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::handleDifferentContent
   * @covers ::saveFileAndCreateEntity
   */
  public function testSetOwnerForExistingFileWithoutOwnerViaSaveFileAndCreateEntity() {
    // Create a test user to use as owner.
    $test_user = $this->createUser();
    $owner_id = $test_user->id();

    // Create destination file that exists but has no owner.
    $destination_file = $this->writeData('old content', 'public://files/test-save-owner.txt', FileExists::Replace);
    $this->assertEquals(0, $destination_file->getOwnerId());

    // Create source file with different content.
    $this->writeData('new content', 'public://source/test-save-owner.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test-save-owner.txt';

    // Resolve with owner_id option.
    // Because source and destination are different files and content differs,
    // this exercises saveFileAndCreateEntity() via existing=Replace.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $owner_id,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($destination_file->id(), $result->id(), 'Destination file entity should be reused');
    $this->assertEquals($owner_id, $result->getOwnerId(), 'File should be owned by the specified owner ID');
  }

  /**
   * Tests that an existing file owner is not overwritten.
   *
   * Flow: Resolves a file path with owner_id option provided. The file entity's
   * owner should not be changed.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::findOrCreateFileEntity
   */
  public function testFileOwnerNoOverwrite() {
    // Create a test user to use as owner.
    $test_user1 = $this->createUser();

    // Create another test user to attempt to use as owner.
    $test_user2 = $this->createUser();

    // Create a source file and set owner.
    $source_file = $this->writeData('test content', 'public://files/test.txt', FileExists::Replace);
    $source_file->setOwnerId($test_user1->id());
    $source_file->save();

    $source_path = $this->getAbsolutePublicDirectoryPath() . '/files/test.txt';

    // Resolve with owner_id option. Note that the destination of the file is
    // the same as on the source, so no file copy should take place.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $test_user2->id(),
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($test_user1->id(), $result->getOwnerId(), 'File owner ID should not be changed');
  }

  /**
   * Tests saveFileAndCreateEntity() does not overwrite existing file owner.
   *
   * Flow: A file entity already exists at destination and has an owner.
   * Resolving a different source file with existing=Replace triggers
   * saveFileAndCreateEntity(), which should keep the existing owner even when
   * a different owner_id option is provided.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::handleDifferentContent
   * @covers ::saveFileAndCreateEntity
   */
  public function testFileOwnerNoOverwriteViaSaveFileAndCreateEntity() {
    // Create a test user as the current owner of destination file.
    $existing_owner = $this->createUser();

    // Create another user that will be passed as owner_id option.
    $requested_owner = $this->createUser();

    // Create destination file and set its owner.
    $destination_file = $this->writeData('old content', 'public://files/test-save-no-overwrite.txt', FileExists::Replace);
    $destination_file->setOwnerId($existing_owner->id());
    $destination_file->save();

    // Create source file with different content.
    $this->writeData('new content', 'public://source/test-save-no-overwrite.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test-save-no-overwrite.txt';

    // Resolve with a different owner_id option.
    // Because source and destination are different files and content differs,
    // this exercises saveFileAndCreateEntity() via existing=Replace.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $requested_owner->id(),
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($destination_file->id(), $result->id(), 'Destination file entity should be reused');
    $this->assertEquals($existing_owner->id(), $result->getOwnerId(), 'File owner ID should not be changed');
  }

  /**
   * Tests saveFileAndCreateEntity() sets current user as owner by default.
   *
   * Flow: A file entity already exists at destination and has owner ID 0.
   * Resolving a different source file with existing=Replace triggers
   * saveFileAndCreateEntity(). Without owner_id option, the file owner should
   * be set to the current user ID.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::handleDifferentContent
   * @covers ::saveFileAndCreateEntity
   */
  public function testSetCurrentUserAsOwnerViaSaveFileAndCreateEntity() {
    $current_user = $this->container->get('current_user');

    // Create destination file that exists but has no owner.
    $destination_file = $this->writeData('old content', 'public://files/test-save-current-owner.txt', FileExists::Replace);
    $this->assertEquals(0, $destination_file->getOwnerId());

    // Create source file with different content.
    $this->writeData('new content', 'public://source/test-save-current-owner.txt', FileExists::Replace);
    $source_path = $this->getAbsolutePublicDirectoryPath() . '/source/test-save-current-owner.txt';

    // Resolve without owner_id option.
    // Because source and destination are different files and content differs,
    // this exercises saveFileAndCreateEntity() via existing=Replace.
    $result = $this->fileResolver->resolve($source_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
    ]);

    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertEquals($destination_file->id(), $result->id(), 'Destination file entity should be reused');
    $this->assertEquals($current_user->id(), $result->getOwnerId(), 'File should be owned by the current user');
  }

  /**
   * Tests findOrCreateFileEntity with owner_id option.
   *
   * Flow: A physical file exists but no file entity. When
   * findOrCreateFileEntity() is called with owner_id option, the new file
   * entity should be created with the specified owner ID.
   *
   * @covers ::resolve
   * @covers ::resolvePath
   * @covers ::findOrCreateFileEntity
   */
  public function testFindOrCreateFileEntityWithOwnerId() {
    $file_system = $this->container->get('file_system');
    $file_repository = $this->container->get('file.repository');

    // Create a physical file directly on disk without creating a file entity.
    $destination_uri = 'public://files/orphan_owner.txt';
    $destination_path = $this->getAbsolutePublicDirectoryPath() . '/files/orphan_owner.txt';

    // Ensure directory exists.
    $directory = 'public://files';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create physical file directly (bypassing file repository).
    file_put_contents($destination_path, 'orphan file content');

    // Verify no file entity exists for this URI.
    $existing_file = $file_repository->loadByUri($destination_uri);
    $this->assertNull($existing_file, 'No file entity should exist for the orphan file');

    // Create a test user to use as owner.
    $test_user = $this->createUser();
    $owner_id = $test_user->id();

    // Resolve with source path pointing to the same location as destination.
    // This will trigger findOrCreateFileEntity().
    $result = $this->fileResolver->resolve($destination_path, [
      'directory' => 'public://files',
      'existing' => FileExists::Replace,
      'owner_id' => $owner_id,
    ]);

    // Verify a new file entity was created.
    $this->assertInstanceOf(FileInterface::class, $result);
    $this->assertNotNull($result->id(), 'File entity should have an ID');

    // Verify the file entity has the specified owner ID.
    $this->assertEquals($owner_id, $result->getOwnerId(), 'File entity should be owned by the specified owner ID');

    // Clean up the physical file.
    unlink($destination_path);
  }

  /**
   * Helper method to write file data.
   *
   * @param string $data
   *   The file content.
   * @param string $destination
   *   The destination URI.
   * @param \Drupal\Core\File\FileExists $replace
   *   How to handle existing files.
   *
   * @return \Drupal\file\FileInterface
   *   The created file entity.
   */
  protected function writeData($data, $destination, FileExists $replace = FileExists::Replace) {
    $file_system = $this->container->get('file_system');
    // Ensure the directory exists.
    $directory = dirname($destination);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    return $this->container->get('file.repository')->writeData($data, $destination, $replace);
  }

}
