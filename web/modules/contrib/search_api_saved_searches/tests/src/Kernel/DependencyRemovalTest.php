<?php

declare(strict_types = 1);

namespace Drupal\Tests\search_api_saved_searches\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api_saved_searches\Entity\SavedSearchType;
use Drupal\search_api_test\PluginTestTrait;

/**
 * Tests that removal of plugin dependencies is handled correctly.
 *
 * @group search_api_saved_searches
 */
class DependencyRemovalTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'help',
    'options',
    'search_api_saved_searches',
    'search_api_saved_searches_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_saved_search');
    $this->installConfig('search_api_saved_searches');
    $this->installSchema('user', 'users_data');
  }

  /**
   * Tests that removal of optional dependencies is handled correctly.
   *
   * @param string $dependency_type
   *   The type of dependency to use, "module" or "config".
   * @param bool $remove
   *   Whether the plugin should successfully adapt upon removal of the
   *   dependency.
   *
   * @dataProvider optionalDependencyRemovalTestDataProvider
   */
  public function testOptionalDependencyRemoval(string $dependency_type, bool $remove): void {
    if ($dependency_type === 'module') {
      $dependency_key = 'module';
      $dependency_name = 'help';
    }
    else {
      assert($dependency_type === 'config');
      // Use a saved search type as the dependency, since we have that available
      // anyways. The entity type should not matter at all, though.
      $dependency = SavedSearchType::create([
        'id' => 'dependency',
        'name' => 'Test dependency',
        'backend' => 'search_api_test',
      ]);
      $dependency->save();
      $dependency_key = $dependency->getConfigDependencyKey();
      $dependency_name = $dependency->getConfigDependencyName();
    }

    $type = SavedSearchType::load('default');
    $plugins = \Drupal::getContainer()
      ->get('plugin.manager.search_api_saved_searches.notification')
      ->createPlugins($type, ['search_api_saved_searches_test'], [
        'search_api_saved_searches_test' => [
          'dependencies' => [
            $dependency_key => [
              $dependency_name,
            ],
          ],
        ],
      ]);
    $type->setNotificationPlugins($plugins);
    $type->save();

    // Check that the dependencies were calculated correctly.
    $type_dependencies = $type->getDependencies();
    $this->assertContains($dependency_name, $type_dependencies[$dependency_key]);

    // Tell the backend plugin whether it should successfully remove the
    // dependency.
    $this->setReturnValue('notification', 'onDependencyRemoval', $remove);

    // Now remove the dependency.
    if (empty($dependency)) {
      \Drupal::getContainer()->get('module_installer')->uninstall(['help']);
    }
    else {
      $dependency->delete();
    }

    // Reload the type.
    $storage = \Drupal::entityTypeManager()->getStorage($type->getEntityTypeId());
    $storage->resetCache();
    $type = $storage->load($type->id());
    $this->assertInstanceOf(SavedSearchType::class, $type);
    $plugins = $type->getNotificationPlugins();

    if ($remove) {
      $this->assertArrayHasKey('search_api_saved_searches_test', $plugins);
      $this->assertArrayNotHasKey('dependencies', $plugins['search_api_saved_searches_test']->getConfiguration());
    }
    else {
      $this->assertArrayNotHasKey('search_api_saved_searches_test', $plugins);
    }
  }

  /**
   * Provides test data sets for testOptionalDependencyRemoval().
   *
   * @return array[]
   *   An associative array of argument arrays for
   *   testOptionalDependencyRemoval(), keyed by data set labels.
   *
   * @see testOptionalDependencyRemoval()
   */
  public static function optionalDependencyRemovalTestDataProvider(): array {
    return [
      ['module', FALSE],
      ['module', TRUE],
      ['config', FALSE],
      ['config', TRUE],
    ];
  }

}
