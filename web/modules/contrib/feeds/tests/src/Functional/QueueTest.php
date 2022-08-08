<?php

namespace Drupal\Tests\feeds\Functional;

use Drupal\feeds\FeedsExecutableInterface;

/**
 * Tests behavior involving the queue.
 *
 * @group feeds
 */
class QueueTest extends FeedsBrowserTestBase {

  /**
   * Tests if a feed gets imported via cron after adding it to the queue.
   */
  public function testCronImport() {
    $feed_type = $this->createFeedType();

    // Create a feed and ensure it gets imported on cron.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);
    $feed->startCronImport();

    // Run cron to import.
    $this->cronRun();

    // Assert that 6 nodes have been created.
    $this->assertNodeCount(6);
  }

  /**
   * Tests if a feed gets imported via a push.
   */
  public function testPushImport() {
    $feed_type = $this->createFeedType();

    // Create a feed without a source.
    $feed = $this->createFeed($feed_type->id());

    // Push file contents.
    $feed->pushImport(file_get_contents($this->resourcesPath() . '/rss/googlenewstz.rss2'));

    // Run cron to import.
    $this->cronRun();

    // Assert that 6 nodes have been created.
    $this->assertNodeCount(6);
  }

  /**
   * Tests on a push import, if only the file pushed is imported.
   */
  public function testPushImportWithSavedSource() {
    $feed_type = $this->createFeedType();

    // Create a feed with a source.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/drupalplanet.rss2',
    ]);

    // Push file contents.
    $feed->pushImport(file_get_contents($this->resourcesPath() . '/rss/googlenewstz.rss2'));

    // Run cron to import.
    $this->cronRun();

    // Assert that 6 nodes have been created.
    $this->assertNodeCount(6);
  }

  /**
   * Tests running an import when a queue task contains a feed object.
   *
   * In Feeds 8.x-3.0-beta2 and lower, when an import was queued, a complete
   * feed object was set on the queue - instead of only a reference to the feed.
   *
   * This test exists for backwards compatibility. People that are updating
   * the Feeds module can still have import tasks on their queue.
   */
  public function testQueueWithFullFeedObject() {
    $feed_type = $this->createFeedType();

    // Create a feed with a source.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);

    // Manually put task on the queue in the way it was done in Feeds
    // 8.x-3.0-beta2 and lower.
    $this->container->get('queue')
      ->get('feeds_feed_refresh:' . $feed->bundle())
      ->createItem([$feed, FeedsExecutableInterface::BEGIN, []]);

    // Run cron to import.
    $this->cronRun();

    // Assert that 6 nodes have been created.
    $this->assertNodeCount(6);
  }

  /**
   * Tests if a feed is removed from the queue when the feed gets deleted.
   */
  public function testQueueAfterDeletingFeed() {
    $feed_type = $this->createFeedType();

    // Create a feed and ensure it gets imported on cron.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);
    $feed->startCronImport();

    // Run cron to import.
    $this->cronRun();

    // Assert that 6 nodes have been created.
    $this->assertNodeCount(6);

    // Unlock the feed manually again, since it still exists in memory.
    // @see \Drupal\Core\Lock\DatabaseLockBackend::acquire()
    $feed->unlock();

    // Add feed to queue again but delete the feed before cron has run.
    $feed->startCronImport();
    $feed->delete();

    // Run cron again.
    $this->cronRun();

    // Assert that the queue is empty.
    $this->assertQueueItemCount(0, 'feeds_feed_refresh:' . $feed_type->id());
  }

  /**
   * Tests feed deletion with a full feed object on the queue.
   *
   * In Feeds 8.x-3.0-beta2 and lower, when an import was queued, a complete
   * feed object was set on the queue - instead of only a reference to the feed.
   *
   * This test exists for backwards compatibility. People that are updating
   * the Feeds module can still have import tasks on their queue.
   */
  public function testQueueAfterDeletingFeedWithFullFeedObject() {
    $feed_type = $this->createFeedType();

    // Create a feed with a source.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesUrl() . '/rss/googlenewstz.rss2',
    ]);

    // Manually put task on the queue in the way it was done in Feeds
    // 8.x-3.0-beta2 and lower.
    $this->container->get('queue')
      ->get('feeds_feed_refresh:' . $feed->bundle())
      ->createItem([$feed, FeedsExecutableInterface::BEGIN, []]);

    // Assert that the item exists on the queue.
    $this->assertQueueItemCount(1, 'feeds_feed_refresh:' . $feed_type->id());

    // Now delete the feed.
    $feed->delete();

    // Run cron to import.
    $this->cronRun();

    // Assert that no nodes have been created.
    $this->assertNodeCount(0);
    // Assert that the queue is empty.
    $this->assertQueueItemCount(0, 'feeds_feed_refresh:' . $feed_type->id());
  }

}
