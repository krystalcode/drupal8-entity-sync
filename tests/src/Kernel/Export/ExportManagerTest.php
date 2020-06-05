<?php

namespace Drupal\Tests\entity_sync\Kernel\Export;

use Drupal\user\Entity\User;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

use Symfony\Component\Yaml\Yaml;

/**
 * Tests the export functionality.
 *
 * @group entity_sync
 */
class ExportManagerTest extends EntityKernelTestBase {

  const FIXTURES_FILENAME = 'entity_sync.sync.user.yml';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'entity_sync',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->config('entity_sync');
    $sync_user_config = Yaml::parse(file_get_contents(
      __DIR__ . '/../../../fixtures/' . self::FIXTURES_FILENAME
    ));
    $config = $sync_user_config['complete'];
    $this->config('entity_sync.sync.user')
      ->setData($config)
      ->save();
  }

  /**
   * Test the hook_entity_insert function.
   */
  public function testExportLocalEntityQueueOnEntityInsert() {
    // Test that on insertion of a new entity, a queue item is created.
    $user = User::create([
      'name' => 'test',
      'mail' => 'test@test.com',
    ]);
    $user->save();
    $queue_name = 'entity_sync_export_local_entity';
    $this->assertEqual(\Drupal::queue($queue_name)->numberOfItems(), 1);
  }

  /**
   * Test the hook_entity_update function.
   */
  public function testExportLocalEntityQueueOnEntityUpdate() {
    // Test that on insertion of a new entity, a queue item is created.
    $user = User::create([
      'name' => 'test',
      'mail' => 'test@test.com',
    ]);
    $user->save();

    // Test that on an entity update, another queue item is created.
    $queue_name = 'entity_sync_export_local_entity';
    $queue = \Drupal::queue($queue_name);
    // The initial create, will produce 1 item in the queue.
    $this->assertEqual($queue->numberOfItems(), 1);
    $user->set('name', 'test2');
    $user->save();
    // The update should produce a second item in the queue.
    $this->assertEqual($queue->numberOfItems(), 2);
  }

}
