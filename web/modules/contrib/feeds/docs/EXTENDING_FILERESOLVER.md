# Extending FileResolver

This document explains how to extend the `FileResolver` service to add custom behavior, such as authentication for URL downloads.

## Use Case: Adding Authentication to URL Downloads

If you previously extended the `File` target plugin and overrode `getContent()` to add authentication, you can now achieve the same by extending `FileResolver`.

## Extend FileResolver

Since `downloadFile()` is a protected method, the best approach is to extend `FileResolver` and override the service.

### Step 1: Create the Extended Class

```php
<?php

namespace Drupal\mymodule\Utility;

use Drupal\feeds\Exception\DownloadException;
use Drupal\feeds\Utility\FileResolver;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds\EntityFinderInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extended FileResolver with authentication support.
 */
class AuthenticatedFileResolver extends FileResolver {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $file_repository = NULL;
    if ($container->has('file.repository')) {
      $file_repository = $container->get('file.repository');
    }

    return new static(
      $container->get('http_client'),
      $container->get('entity_type.manager'),
      $container->get('feeds.entity_finder'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $file_repository
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function downloadFile(string $url): string {
    try {
      // Add authentication headers or other custom request options.
      $options = [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->getAuthToken($url),
        ],
      ];

      $response = $this->client->request('GET', $url, $options);
      if ($response->getStatusCode() >= 400) {
        throw new DownloadException($url, $response->getStatusCode());
      }
      return (string) $response->getBody();
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      if ($response !== NULL) {
        throw new DownloadException($url, $response->getStatusCode(), NULL, $e);
      }
      throw new DownloadException($url, NULL, $e->getMessage(), $e);
    }
    catch (RequestException $e) {
      throw new DownloadException($url, NULL, $e->getMessage(), $e);
    }
  }

  /**
   * Gets the authentication token for a URL.
   *
   * @param string $url
   *   The URL to get the token for.
   *
   * @return string
   *   The authentication token.
   */
  protected function getAuthToken(string $url): string {
    // Your custom logic to get the authentication token.
    // This could be from configuration, a service, etc.
    return 'your-auth-token';
  }

}
```

### Step 2: Decorate the Service

In `mymodule.services.yml`:

```yaml
services:
  mymodule.file_resolver:
    class: Drupal\mymodule\Utility\AuthenticatedFileResolver
    decorates: feeds.file_resolver
    factory: Drupal\mymodule\Utility\AuthenticatedFileResolver::create
    arguments: ['@service_container']
```

## Migration from getContent() Override

If you previously overrode `getContent()` in a File target subclass:

**Backward compatibility:** During the deprecation period (until feeds:4.0.0), your override continues to work without changes. The File target detects when `getFileName()`, `getContent()`, or `writeData()` is overridden and uses the legacy code path for URL values. No immediate migration is required. Note: deprecation warnings will still be triggered for the legacy methods. And you would be missing new file resolving features that are added to FileResolver.

**Before (still works during deprecation):**
```php
protected function getContent($url) {
  $response = $this->client->request('GET', $url, [
    'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
  ]);
  // ...
}
```

**After (when migrating):** Extend FileResolver and override `downloadFile()` instead.
