<?php

namespace Drupal\Tests\entity_sync\Unit;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Export\Manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @coversDefaultClass \Drupal\entity_sync\Export\Manager
 * @group entity_sync
 */
class ExportManagerTest extends ManagerTestBase {

  const FIXTURES_FILENAME = 'entity_sync.sync.user.yml';
  const SYNC_ID = 'user';

  /**
   * Data providers.
   */

  /**
   * Data provider for the sync configurations.
   */
  public function syncCaseDataProvider() {
    return [
      'complete',
      'operations_disabled',
    ];
  }

  /**
   * Prepares all possible combinations of data from other providers.
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
    ];

    $data = [];

    // Initialize with the first provider's data.
    $provider = $providers[0];
    unset($providers[0]);
    foreach ($this->{$provider}() as $data_instance) {
      $data[] = [$data_instance];
    }

    foreach ($providers as $provider) {
      $data_copy = $data;
      $data = [];

      foreach ($this->{$provider}() as $index => $data_instance) {
        foreach ($data_copy as $data_item) {
          array_push($data_item, $data_instance);
          $data[] = $data_item;
        }
      }
    }

    return $data;
  }

  /**
   * Tests the entire exportLocalEntity() function.
   *
   * ::covers exportLocalEntity.
   *
   * @param string $sync_case
   *   The particular sync case, ie. 'complete', 'operations_disabled', etc.
   *
   * @test
   * @dataProvider dataProvider
   */
  public function testAll(string $sync_case) {
    // Mock services required for instantiating the export manager.
    $client_factory = $this->prophesize(ClientFactory::class);
    $entity_type_manager = $this->prophesize(
      EntityTypeManagerInterface::class
    );
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $sync = $this->prophesize(ImmutableConfig::class);
    $local_entity = $this->prophesize(EntityInterface::class);

    // Register event subscribers that will be setting the given values.
    $event_dispatcher = new EventDispatcher();

    // In all cases we will be loading the Sync configuration and at the very
    // least checking whether the operation is supported.
    $operation_status_key = 'operations.export_entity.status';
    $operation_status = $this->getSyncProperty(
      self::FIXTURES_FILENAME,
      $sync_case,
      $operation_status_key
    );
    $sync
      ->get($operation_status_key)
      ->willReturn($operation_status)
      ->shouldBeCalledTimes(1);
    $config_factory = $this->prophesize(
      ConfigFactoryInterface::class
    );
    $config_factory
      ->get('entity_sync.sync.' . self::SYNC_ID)
      ->willReturn($sync);

    // Prepare the test case context as an array so we can easily pass it around
    // to branch methods.
    $test_context = [
      'client_factory' => $client_factory,
      'config_factory' => $config_factory,
      'entity_type_manager' => $entity_type_manager,
      'logger' => $logger,
      'local_entity' => $local_entity,
      'sync' => $sync,
      'sync_case' => $sync_case,
    ];

    // Add our first case of assertions for the config.
    $this->branchSupportedOperation($test_context, $operation_status);
    $this->branchUnsupportedOperation($test_context, $operation_status);

    // Initialize the export manager and call the exportLocalEntity() function.
    $manager = new Manager(
      $client_factory->reveal(),
      $config_factory->reveal(),
      $entity_type_manager->reveal(),
      $event_dispatcher,
      $logger->reveal()
    );
    $manager->exportLocalEntity(
      self::SYNC_ID,
      $local_entity->reveal(),
      []
    );
  }

  /**
   * Assert that we're properly handling unsupported operations.
   *
   * @param array $test_context
   *   The test case context.
   * @param bool $operation_status
   *   The operation status.
   */
  private function branchUnsupportedOperation(
    array $test_context,
    $operation_status
  ) {
    if ($operation_status) {
      return;
    }

    $test_context['logger']
      ->error(Argument::any())
      ->shouldBeCalledTimes(1);
  }

  /**
   * Assert that we're properly handling supported operations.
   *
   * @param array $test_context
   *   The test case context.
   * @param bool $operation_status
   *   The operation status.
   */
  private function branchSupportedOperation(
    array $test_context,
    $operation_status
  ) {
    if (!$operation_status) {
      return;
    }

    $test_context['logger']
      ->error(Argument::any())
      ->shouldNotBeCalled();
  }

}
