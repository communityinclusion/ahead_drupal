<?php

namespace Drupal\search_api_page\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Search API Page path processor.
 */
class PathProcessorSearchApiPage implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Cached clean URL paths for the inbound processor.
   *
   * @var string[]|null
   */
  protected $cleanUrlPaths;

  /**
   * PathProcessorSearchApiPage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager, ConfigFactoryInterface $config) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    foreach ($this->getSearchApiPagePathsUsingCleanUrls() as $search_api_clean_url_path) {
      $regex = '~^' . $search_api_clean_url_path . '~';
      if (preg_match($regex, $path)) {
        $keys = str_replace($search_api_clean_url_path, '', $path);
        return $search_api_clean_url_path . rawurlencode($keys);
      }
    }
    return $path;
  }

  /**
   * Get Search API page path for clean urls.
   */
  protected function getSearchApiPagePathsUsingCleanUrls() {
    if ($this->cleanUrlPaths !== NULL) {
      return $this->cleanUrlPaths;
    }

    $paths = [];
    $is_multilingual = $this->languageManager->isMultilingual();
    $all_languages = $this->languageManager->getLanguages();

    /** @var \Drupal\search_api_page\SearchApiPageInterface $search_api_page */
    foreach ($this->entityTypeManager->getStorage('search_api_page')
      ->loadMultiple() as $search_api_page) {
      if (!$search_api_page->getCleanUrl()) {
        continue;
      }

      // Default path.
      $default_path = $search_api_page->getPath();

      // Loop over all languages so we can get the translated path (if any).
      foreach ($all_languages as $language) {
        $path = '';

        // Check if we are multilingual or not.
        if ($is_multilingual) {
          $path = $this->languageManager
            ->getLanguageConfigOverride($language->getId(), 'search_api_page.search_api_page.' . $search_api_page->id())
            ->get('path');
        }
        if (empty($path)) {
          $path = $default_path;
        }

        $paths[] = '/' . $path . '/';
      }
    }

    $this->cleanUrlPaths = $paths;
    return $this->cleanUrlPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if ($request === NULL || $path === "/") {
      return $path;
    }

    if (strpos($request->attributes->get('_route', ''), 'search_api_page.') !== 0) {
      return $path;
    }

    // Skip processing of no 'Search API Page' routes.
    // Cannot inject path.validator due to circular dependency through router.
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    $url_object = \Drupal::service('path.validator')->getUrlIfValid($path);
    if ($url_object && strpos($url_object->getRouteName(), 'search_api_page.') !== 0) {
      return $path;
    }

    if (!isset($options['language']) || empty($options['language'])) {
      return $path;
    }

    $search_api_page_id = $request->attributes->get('search_api_page_name');
    $config_name = 'search_api_page.search_api_page.' . $search_api_page_id;
    $original_language = $this->languageManager->getConfigOverrideLanguage();
    $this->languageManager->setConfigOverrideLanguage($options['language']);
    $path = $this->config->get($config_name)->get('path');
    $this->languageManager->setConfigOverrideLanguage($original_language);

    // Preserve keys when switching between languages.
    if ($request->attributes->get('keys')) {
      $path .= '/' . $request->attributes->get('keys');
    }

    if (strpos($path ?? '', '/') !== 0) {
      return '/' . $path;
    }

    return $path;
  }

}
