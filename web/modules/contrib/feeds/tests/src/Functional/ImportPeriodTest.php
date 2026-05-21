<?php

namespace Drupal\Tests\feeds\Functional;

use Drupal\feeds\Entity\FeedType;
use Drupal\feeds\FeedTypeInterface;

/**
 * Tests import period settings work with the Feeds module.
 *
 * @group feeds
 */
class ImportPeriodTest extends FeedsBrowserTestBase {

  /**
   * Tests that the date for the next import time is displayed on the feed.
   */
  public function testDisplayNextImport() {
    // Create a feed type with periodic import set to hourly.
    $feed_type = $this->createFeedType([
      'import_period' => 3600,
    ]);

    // Create a feed.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);

    // Assert that it now says that the import happens on the next cron run.
    $this->drupalGet('/feed/1');
    $this->assertSession()->pageTextContains('On next cron run');
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');

    // Run cron to run the import.
    $this->cronRun();

    // Assert that the next import time is displayed.
    $feed = $this->reloadEntity($feed);
    $next_import = $this->container->get('date.formatter')->format($feed->getImportedTime() + 3600);
    $this->drupalGet('/feed/1');
    $this->assertSession()->pageTextContains($next_import);
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');
  }

  /**
   * Tests that an unscheduled feed does not display a date.
   */
  public function testDisplayNextImportWithPeriodicImportOff() {
    $feed_type = $this->createFeedType([
      'import_period' => FeedTypeInterface::SCHEDULE_NEVER,
    ]);

    // Create a feed and import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);
    $feed->import();

    // Assert that no next import time is displayed.
    $this->drupalGet('/feed/1');
    $this->assertSession()->pageTextContains('Not scheduled');
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');
  }

  /**
   * Tests next import time after scheduling an import.
   */
  public function testDisplayNextImportWhenSchedulingManually() {
    $feed_type = $this->createFeedType([
      'import_period' => FeedTypeInterface::SCHEDULE_NEVER,
    ]);

    // Create a feed and import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);

    $this->drupalGet('/feed/1/schedule-import');
    $this->submitForm([], 'Schedule import');

    $this->assertSession()->pageTextContains('On next cron run');
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');
  }

  /**
   * Tests next import time after scheduling an import for an inactive feed.
   */
  public function testDisplayNextImportWhenSchedulingManuallyForInactiveFeed() {
    $feed_type = $this->createFeedType([
      'import_period' => FeedTypeInterface::SCHEDULE_NEVER,
    ]);

    // Create a feed and import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);
    $feed->setActive(FALSE);
    $feed->save();

    $this->drupalGet('/feed/1/schedule-import');
    $this->submitForm([], 'Schedule import');

    $this->assertSession()->pageTextContains('On next cron run');
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');
  }

  /**
   * Tests next import time when periodic import is disabled for the feed.
   */
  public function testDisplayNextImportWithInactiveFeed() {
    $feed_type = $this->createFeedType([
      'import_period' => 3600,
    ]);

    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);

    // Deactivate periodic import for this feed, but do set a next import time.
    $feed->setActive(FALSE);
    $feed->set('next', \Drupal::time()->getRequestTime() + 3600);
    $feed->save();

    // Assert that no next import time is displayed.
    $this->drupalGet('/feed/1');
    $this->assertSession()->pageTextContains('Not scheduled');
    $this->assertSession()->pageTextNotContains('On next cron run');
    $this->assertSession()->pageTextNotContains('1969');
    $this->assertSession()->pageTextNotContains('1970');
  }

  /**
   * Tests that import period per feed setting is enabled and displayed.
   */
  public function testImportPeriodPerFeedEnabled() {
    // Create a feed type using the upload fetcher.
    $feed_type = $this->createFeedType([
      'import_period_per_feed' => TRUE,
    ]);

    // Assert that settings are saved.
    $feed_type = FeedType::load($feed_type->id());
    $this->assertTrue($feed_type->isImportPeriodPerFeedAllowed());

    // Check feed creation.
    $this->drupalGet('feed/add/' . $feed_type->id());

    // Ensure that the import period per feed field is present and contains
    // the expected options.
    $this->assertSession()->fieldExists('periodic_import');
    $this->assertSession()->optionExists('periodic_import', -2);
    $this->assertSession()->optionExists('periodic_import', -1);
    $this->assertSession()->optionExists('periodic_import', 0);
    $this->assertSession()->optionExists('periodic_import', 900);
    $this->assertSession()->optionExists('periodic_import', 3600);
    $this->assertSession()->optionExists('periodic_import', 2419200);

    // Create a feed with periodic import.
    $source_file = $this->resourcesPath() . '/csv/content.csv';
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      'plugin[fetcher][source]' => $this->container->get('file_system')->realpath($source_file),
      'periodic_import' => 3600,
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Tests that import period per feed setting is disabled and not displayed.
   */
  public function testImportPeriodPerFeedDisabled() {
    // Create a feed type using the upload fetcher.
    $feed_type = $this->createFeedType([
      'import_period_per_feed' => FALSE,
    ]);

    // Check feed creation.
    $this->drupalGet('feed/add/' . $feed_type->id());

    // Ensure that the import period per feed field is not present.
    $this->assertSession()->fieldNotExists('periodic_import');
  }

}
