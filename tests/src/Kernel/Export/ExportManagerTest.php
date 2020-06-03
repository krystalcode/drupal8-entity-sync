<?php

namespace Drupal\Tests\entity_sync\Kernel\Export;

use Drupal\user\Entity\User;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the export functionality.
 *
 * @group entity_sync
 */
class ExportManagerTest extends KernelTestBase {

  const FIXTURES_FILENAME = 'entity_sync.sync.user.yml';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'entity_sync',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('entity_sync', ['entity_sync']);
    $sync_user_config = file_get_contents(
      __DIR__ . '/../../fixtures/' . FIXTURES_FILENAME
    );
    $this->config('entity_sync.sync.user')
      ->set($sync_user_config)
      ->save();
  }

  /**
   * Test the hook_entity_insert function.
   */
  public function testExportLocalEntityQueueOnEntityInsert() {
    // Test that on insertion of a new entity, a queue item is created.
    $queue_name = 'entity_sync_export_local_entity';
    $user = User::create([
      'name' => 'test',
      'mail' => 'test@test.com',
    ]);
    $user = $user->save();
    $this->assertEqual(\Drupal::queue($queue_name)->numberOfItems(), 1);
  }

  /**
   * Test the hook_entity_update function.
   */
  public function testExportLocalEntityQueueOnEntityUpdate() {
    // Test that on insertion of a new entity, a queue item is created.
    $queue_name = 'entity_sync_export_local_entity';
    $user = User::create([
      'name' => 'test',
      'mail' => 'test@test.com',
    ]);
    $user = $user->save();
    $this->assertEqual(\Drupal::queue($queue_name)->numberOfItems(), 1);

    // Test that on an entity update, another queue item is created.
    $user->set('name', 'test2');
    $user->save();
    $this->assertEqual(\Drupal::queue($queue_name)->numberOfItems(), 2);
  }

}