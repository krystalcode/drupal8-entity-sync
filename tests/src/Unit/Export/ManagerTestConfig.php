<?php

use Drupal\entity_sync\Export\Manager;

use Drupal\Tests\entity_sync\Unit\Export\ManagerTestBase;

/**
 * Tests the configuration logic for the export manager class.
 *
 * @coversDefaultClass \Drupal\entity_sync\Export\Manager
 * @group entity_sync
 */
class ManagerTestConfig extends ManagerTestBase {

  /**
   * Tests the operation supported logic.
   *
   * ::covers exportLocalEntity.
   */
  public function testExportLocalEntityOperationSupported() {
    // Test when 'export_entity' operation is supported.
    // Fetch the sample config.
    $config = $this->getConfigFixture(
      'entity_sync.sync.' . ManagerTestBase::SYNC_ID . '.yml'
    );

    // Initialize manger.
    $manager = $this->getManager(ManagerTestBase::SYNC_ID, $config);
    $this->assertTrue($manager->exportLocalEntity(
      ManagerTestBase::SYNC_ID,
      $this->localEntity
    ));

    // Test when 'export_entity' operation is set to FALSE.
    // Remove the 'export_entity' key from the config.
    $config['operations']['export_entity']['status'] = FALSE;
    // Initialize manger.
    $manager = $this->getManager(ManagerTestBase::SYNC_ID, $config);
    $this->assertNull($manager->exportLocalEntity(
      ManagerTestBase::SYNC_ID,
      $this->localEntity
    ));

    // Test when 'export_entity' operation is NOT supported at all.
    // Remove the 'export_entity' key from the config.
    unset($config['operations']['export_entity']);
    // Initialize manger.
    $manager = $this->getManager(ManagerTestBase::SYNC_ID, $config);
    $this->assertNull($manager->exportLocalEntity(
      ManagerTestBase::SYNC_ID,
      $this->localEntity
    ));
  }

  /**
   * Returns an initialized export manager given a sync_id and config array.
   *
   * @param string $sync_id
   *   The sync ID.
   * @param mixed $config
   *   A YAML converted to a PHP value.
   *
   * @return \Drupal\entity_sync\Export\Manager
   *   The export manager.
   */
  private function getManager($sync_id, $config) {
    return new Manager(
      $this->clientFactory,
      $this->getConfigFactory($sync_id, $config),
      $this->entityTypeManager,
      $this->eventDispatcher,
      $this->logger
    );
  }

}
