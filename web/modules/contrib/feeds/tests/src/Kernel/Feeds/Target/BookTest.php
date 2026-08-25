<?php

namespace Drupal\Tests\feeds\Kernel\Feeds\Target;

use Drupal\Tests\feeds\Kernel\FeedsKernelTestBase;
use Drupal\book\Entity\Node\Book as NodeBook;
use Drupal\feeds\Plugin\Type\Processor\ProcessorInterface;
use Drupal\node\Entity\Node;

/**
 * Tests for the book target.
 *
 * @group feeds
 */
class BookTest extends FeedsKernelTestBase {

  /**
   * The feed type.
   *
   * @var \Drupal\feeds\FeedTypeInterface
   */
  protected $feedType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'book',
    'text',
    'filter',
    'feeds',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('book', 'book');
    $this->installConfig(['book']);
    $this->configureArticleAsBookType();

    // Create a feed type.
    $this->feedType = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'book_id' => 'book_id',
      'parent_id' => 'parent_id',
    ]);
  }

  /**
   * Allows the article content type to be used in book outlines.
   *
   * Drupal 10 core Book stores allowed types as a flat list of bundle names.
   * Drupal 11+ contrib Book uses a list of content_type/child_type pairs and
   * only assigns the Book node bundle class to those allowed types.
   */
  protected function configureArticleAsBookType(): void {
    $config = $this->config('book.settings');

    // Contrib Book (Drupal 11+) provides a dedicated node bundle class.
    if (class_exists(NodeBook::class)) {
      $config->set('allowed_types', [
        [
          'content_type' => 'article',
          'child_type' => 'article',
        ],
      ])->save();
    }
    else {
      $allowed_types = $config->get('allowed_types') ?? [];
      if (!in_array('article', $allowed_types, TRUE)) {
        $allowed_types[] = 'article';
        $config->set('allowed_types', $allowed_types)->save();
      }
    }

    // Rebuild bundle info so the Book node class is assigned when applicable.
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->entityTypeManager->clearCachedDefinitions();
  }

  /**
   * Returns book outline data from a node.
   *
   * @param \Drupal\node\NodeInterface|\Drupal\Core\Entity\EntityInterface|null $node
   *   The node entity.
   *
   * @return array|null
   *   The book data, or NULL when the node is not part of a book.
   */
  protected function getNodeBook($node): ?array {
    if (!$node) {
      return NULL;
    }
    if (method_exists($node, 'getBook')) {
      $book = $node->getBook();
      return !empty($book) ? $book : NULL;
    }
    return $node->book ?? NULL;
  }

  /**
   * Adds a book mapper to the feed type.
   */
  protected function addBookMapper() {
    $this->feedType->addMapping([
      'target' => 'book',
      'map' => [
        'bid' => 'book_id',
        'pid' => 'parent_id',
      ],
    ]);
    $this->feedType->save();
  }

  /**
   * Creates a new book node.
   *
   * @param int $nid
   *   (optional) The node ID of the book. Defaults to '1'.
   * @param array $values
   *   (optional) The node values.
   */
  protected function createBookNode($nid = 1, array $values = []) {
    $values += [
      'nid' => $nid,
      'title' => 'Book Foo',
      'type' => 'article',
      'book' => [
        'bid' => $nid,
        'pid' => -1,
      ],
    ];

    Node::create($values)->save();
  }

  /**
   * Creates a new child page for a book.
   *
   * @param int $book_id
   *   (optional) The node ID of the book. Defaults to '1'.
   * @param int $parent_id
   *   (optional) The parent node ID of the page. Defaults to '1'.
   * @param array $values
   *   (optional) The node values.
   */
  protected function createChildPage($book_id = 1, $parent_id = 1, array $values = []) {
    $values += [
      'title' => 'Chapter 1',
      'type' => 'article',
      'book' => [
        'bid' => $book_id,
        'pid' => $parent_id,
      ],
    ];

    Node::create($values)->save();
  }

  /**
   * Tests importing a new node in a book.
   */
  public function testInsert() {
    // Create a book and a child page.
    $this->createBookNode();
    $this->createChildPage();

    // Add book mapper to feed type.
    $this->addBookMapper();

    // Import.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);
    $feed->import();

    $node = Node::load(3);
    $book = $this->getNodeBook($node);
    $this->assertEquals(1, $book['bid']);
    $this->assertEquals(2, $book['pid']);
  }

  /**
   * Tests importing a node in a book, specifying only the parent node.
   */
  public function testInsertByParentNode() {
    // Create a book and a child page.
    $this->createBookNode(5);
    $this->createChildPage(5, 5, [
      'nid' => 2,
    ]);

    // Add a mapper to book, but do not map to book ID.
    $this->feedType->addMapping([
      'target' => 'book',
      'map' => [
        'pid' => 'parent_id',
      ],
    ]);
    $this->feedType->save();

    // Import.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);
    $feed->import();

    // Assert that the node got a reference to book, taken from the parent.
    $node = Node::load(6);
    $book = $this->getNodeBook($node);
    $this->assertEquals(5, $book['bid']);
    $this->assertEquals(2, $book['pid']);
  }

  /**
   * Tests updating a node in a book, setting a different parent.
   */
  public function testUpdate() {
    // Create a book and a child page.
    $this->createBookNode();
    $this->createChildPage();

    // Add book mapper to feed type.
    $this->addBookMapper();

    // Set feed type to update existing nodes.
    $configuration = $this->feedType->getProcessor()->getConfiguration();
    $configuration['update_existing'] = ProcessorInterface::UPDATE_EXISTING;
    $this->feedType->getProcessor()->setConfiguration($configuration);
    $this->feedType->save();

    // Create a new child page for the book.
    Node::create([
      'title' => 'Chapter 2',
      'type' => 'article',
      'book' => [
        'bid' => 1,
        'pid' => 1,
      ],
    ])->save();

    // Create a feed.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);

    // Create the node to update.
    $node = $this->createNodeWithFeedsItem($feed, [
      'book' => [
        'bid' => 1,
        'pid' => 3,
      ],
    ]);

    $feed->import();

    $node = $this->reloadEntity($node);
    $book = $this->getNodeBook($node);
    $this->assertEquals(1, $book['bid']);
    $this->assertEquals(2, $book['pid']);
  }

  /**
   * Tests import resulting into creating a new top level book.
   */
  public function testCreateNewBook() {
    // Create a feed type. Only map to the property "new".
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'new' => 'new',
    ]);
    $feed_type->addMapping([
      'target' => 'book',
      'map' => [
        'new' => 'new',
      ],
    ]);
    $feed_type->save();

    // Import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);
    $feed->import();

    // Assert that the imported node is now the top level page.
    $node = Node::load(1);
    $book = $this->getNodeBook($node);
    $this->assertEquals(1, $book['bid']);
    $this->assertEquals(0, $book['pid']);
    $this->assertEquals(1, $book['depth']);
  }

  /**
   * Tests updating an existing book with mapping to "new".
   */
  public function testUpdateWithPropertyNew() {
    $this->installConfig(['field', 'filter', 'node']);
    $this->createFieldWithStorage('field_alpha');

    // Create a feed type. Map to the property "new" of "book" and map to
    // field_alpha.
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'new' => 'new',
    ]);
    $feed_type->addMapping([
      'target' => 'book',
      'map' => [
        'new' => 'new',
      ],
    ]);
    $feed_type->addMapping([
      'target' => 'field_alpha',
      'map' => ['value' => 'alpha'],
    ]);

    // Set feed type to update existing nodes.
    $configuration = $feed_type->getProcessor()->getConfiguration();
    $configuration['update_existing'] = ProcessorInterface::UPDATE_EXISTING;
    $feed_type->getProcessor()->setConfiguration($configuration);

    $feed_type->save();

    // Import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);

    // Create the node to update.
    $node = $this->createNodeWithFeedsItem($feed, [
      'book' => [
        'bid' => 1,
        'pid' => 0,
      ],
    ]);

    $feed->import();

    // Assert that the imported node is still the top level page and that the
    // field 'field_alpha' now has a value.
    $node = $this->reloadEntity($node);
    $book = $this->getNodeBook($node);
    $this->assertEquals(1, $book['bid']);
    $this->assertEquals(0, $book['pid']);
    $this->assertEquals(1, $book['depth']);
    $this->assertEquals('Foo', $node->field_alpha->value);

    // Assert that there are no warnings or messages.
    $messages = \Drupal::messenger()->all();
    $this->assertArrayNotHasKey('warning', $messages);
    $this->assertArrayNotHasKey('error', $messages);
  }

  /**
   * Tests importing a book hierarchy.
   *
   * The file 'content-book-hierarchy.csv' contains two nodes called 'Chapter 1'
   * (item 2 and 4). The first one belongs to book 'Book 1' and the second one
   * to book 'Book 2'. Item 5 ('Paragraph 1') references a parent called
   * 'Chapter 1', but it also specifies a book called 'Book 2'. Expected is that
   * item 5 gets linked to parent 4 (and not 2).
   */
  public function testImportBookHierarchy() {
    $this->feedType->addCustomSource('new', [
      'label' => 'New',
      'value' => 'new',
      'type' => 'csv',
    ]);
    $this->feedType->addMapping([
      'target' => 'book',
      'map' => [
        'bid' => 'book_id',
        'pid' => 'parent_id',
        'new' => 'new',
      ],
      'settings' => [
        'book_reference_by' => 'title',
        'parent_reference_by' => 'title',
      ],
    ]);
    $this->feedType->save();

    // Create a feed and import.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book-hierarchy.csv',
    ]);
    $feed->import();

    $expected_book_values = [
      1 => [
        'title' => 'Book 1',
        'bid' => 1,
        'pid' => 0,
        'depth' => 1,
      ],
      2 => [
        'title' => 'Chapter 1',
        'bid' => 1,
        'pid' => 1,
        'depth' => 2,
      ],
      3 => [
        'title' => 'Book 2',
        'bid' => 3,
        'pid' => 0,
        'depth' => 1,
      ],
      4 => [
        'title' => 'Chapter 1',
        'bid' => 3,
        'pid' => 3,
        'depth' => 2,
      ],
      5 => [
        'title' => 'Paragraph 1',
        'bid' => 3,
        'pid' => 4,
        'depth' => 3,
      ],
    ];
    foreach ($expected_book_values as $nid => $values) {
      $node = Node::load($nid);
      $this->assertEquals($values['title'], $node->title->value, "Title for node $nid is " . $values['title']);
      $book = $this->getNodeBook($node);
      $this->assertEquals($values['bid'], $book['bid'], "Book ID for node $nid is " . $values['bid']);
      $this->assertEquals($values['pid'], $book['pid'], "Parent ID for node $nid is " . $values['pid']);
      $this->assertEquals($values['depth'], $book['depth'], "Depth for node $nid is " . $values['depth']);
    }
  }

  /**
   * Tests removing a node from a book.
   */
  public function testRemoveFromBook() {
    // Create a book and a child page.
    $this->createBookNode();
    $this->createChildPage();

    // Add book mapper to feed type.
    $this->addBookMapper();

    // Set feed type to update existing nodes.
    $configuration = $this->feedType->getProcessor()->getConfiguration();
    $configuration['update_existing'] = ProcessorInterface::UPDATE_EXISTING;
    $this->feedType->getProcessor()->setConfiguration($configuration);
    $this->feedType->save();

    // Import first file.
    $feed = $this->createFeed($this->feedType->id(), [
      'source' => $this->resourcesPath() . '/csv/content-book.csv',
    ]);
    $feed->import();

    // Import an "updated" version of the file in which there are empty values
    // for book.
    $feed->setSource($this->resourcesPath() . '/csv/content-book-empty.csv');
    $feed->save();
    $feed->import();

    $node = Node::load(3);
    $this->assertNull($this->getNodeBook($node));
  }

  /**
   * Tests if nodes are updated that previously referenced a non-existing book.
   *
   * When a referenced item does not exist yet, Feeds should try to set the
   * reference on a second import, because it is possible the referenced item
   * may exist by then.
   *
   * Feeds usually skips importing a source item if it did not change since the
   * previous import, but in case of previously missing references, it should do
   * not.
   */
  public function testUpdatingMissingBookReference() {
    // Create a feed type. Do not map to parent ID.
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'alpha' => 'alpha',
    ]);
    $feed_type->addMapping([
      'target' => 'book',
      'map' => [
        'bid' => 'alpha',
      ],
      'settings' => [
        'book_reference_by' => 'title',
      ],
    ]);

    // Set feed type to update existing nodes.
    $configuration = $feed_type->getProcessor()->getConfiguration();
    $configuration['update_existing'] = ProcessorInterface::UPDATE_EXISTING;
    $feed_type->getProcessor()->setConfiguration($configuration);

    $feed_type->save();

    // Import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();

    // Assert that the imported node does not have book details yet.
    $node = Node::load(1);
    $this->assertNull($this->getNodeBook($node));

    // Now create the book that is referenced.
    $this->createBookNode(4, [
      'title' => 'Lorem',
    ]);

    // Import again and assert that there's now a book reference.
    $feed->import();
    $node = $this->reloadEntity($node);
    $book = $this->getNodeBook($node);
    $this->assertEquals(4, $book['bid']);
    $this->assertEquals(4, $book['pid']);

    // Clear the logged messages so no failure is reported on tear down.
    $this->logger->clearMessages();
  }

  /**
   * Tests if nodes are updated that previously referenced a non-existing node.
   */
  public function testUpdatingMissingParentNode() {
    $this->createBookNode(42);

    // Create a feed type.
    $feed_type = $this->createFeedTypeForCsv([
      'guid' => 'guid',
      'title' => 'title',
      'alpha' => 'alpha',
      'beta' => 'beta',
    ]);
    $feed_type->addMapping([
      'target' => 'book',
      'map' => [
        'bid' => 'beta',
        'pid' => 'alpha',
      ],
      'settings' => [
        'parent_reference_by' => 'title',
      ],
    ]);

    // Set feed type to update existing nodes.
    $configuration = $feed_type->getProcessor()->getConfiguration();
    $configuration['update_existing'] = ProcessorInterface::UPDATE_EXISTING;
    $feed_type->getProcessor()->setConfiguration($configuration);

    $feed_type->save();

    // Import.
    $feed = $this->createFeed($feed_type->id(), [
      'source' => $this->resourcesPath() . '/csv/content.csv',
    ]);
    $feed->import();

    // Assert that the imported node does not have book details yet.
    $node = Node::load(43);
    $this->assertNull($this->getNodeBook($node));

    // Now create the child page that is referenced.
    $this->createChildPage(42, 42, [
      'nid' => 8,
      'title' => 'Lorem',
    ]);

    // Import again and assert that there's now a book reference.
    $feed->import();
    $node = $this->reloadEntity($node);
    $book = $this->getNodeBook($node);
    $this->assertEquals(42, $book['bid']);
    $this->assertEquals(8, $book['pid']);

    // Clear the logged messages so no failure is reported on tear down.
    $this->logger->clearMessages();
  }

}
