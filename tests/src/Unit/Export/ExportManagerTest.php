<?php

namespace Drupal\Tests\entity_sync\Unit;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\Export\Manager;
use Drupal\entity_sync\Export\ManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
   * Data provider for the entity mapping set via events subscribers.
   *
   * @I Add tests for invalid entity mapping data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  public function eventEntityMappingDataProvider() {
    return [
      // No entity mapping.
      [],
      // Skip action.
      [
        'action' => ManagerInterface::ACTION_SKIP,
        'client' => 'my_module.entity_sync_client.user',
        'id' => 1,
      ],
      // Invalid action.
      [
        'action' => 'unsupported-action',
        'client' => 'my_module.entity_sync_client.user',
        'id' => 1,
      ],
    ];
  }

  /**
   * Prepares all possible combinations of data from other providers.
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
      'eventEntityMappingDataProvider',
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
   * @param array $event_entity_mapping
   *   The entity mapping info.
   *
   * @test
   * @dataProvider dataProvider
   */
  public function testAll(
    string $sync_case,
    array $event_entity_mapping
  ) {
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
    $event_dispatcher->addSubscriber(
      $this->buildEntityMappingEventSubscriber($event_entity_mapping)
    );

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
      'event_entity_mapping' => $event_entity_mapping,
    ];

    // Add our first case of assertions for the config.
    $this->branchUnsupportedOperation($test_context, $operation_status);
    $this->branchSupportedOperation($test_context, $operation_status);

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

    // Continue on with the next set of tests.
    $this->branchEmptyEntityMapping($test_context);
    $this->branchSkipAction($test_context);
    $this->branchUnsupportedAction($test_context);
  }

  /**
   * Assert that we return if the entity mapping array is empty.
   *
   * @param array $test_context
   *   The test case context.
   */
  private function branchEmptyEntityMapping(array $test_context) {
    if ($test_context['event_entity_mapping']) {
      return;
    }

    // @I Assert that the function doesn't continue on
    //    type     : task
    //    priority : normal
    //    labels   : test
  }

  /**
   * Assert that we return if the entity mapping array returned a SKIP action.
   *
   * @param array $test_context
   *   The test case context.
   */
  private function branchSkipAction(array $test_context) {
    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping) {
      return;
    }

    if ($mapping && $mapping['action'] !== ManagerInterface::ACTION_SKIP) {
      return;
    }

    // @I Assert that the function doesn't continue on
    //    type     : task
    //    priority : normal
    //    labels   : test
  }

  /**
   * Assert that we throw an exception on entity mapping with an illegal action.
   *
   * @param array $test_context
   *   The test case context.
   */
  private function branchUnsupportedAction(array $test_context) {
    $supported_actions = [
      ManagerInterface::ACTION_SKIP,
      ManagerInterface::ACTION_EXPORT,
    ];

    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping || in_array($mapping['action'], $supported_actions, TRUE)) {
      return;
    }

    // Unsupported actions will result in an exception.
    $this->expectException(\RuntimeException::class);
  }

  /**
   * Builds an entity mapping event subscriber on the fly.
   *
   * @param array $entity_mapping
   *   The entity mapping info to set.
   */
  private function buildEntityMappingEventSubscriber(array $entity_mapping) {
    return new class($entity_mapping) implements EventSubscriberInterface {
      private $entityMapping;

      public function __construct(array $entity_mapping) {
        $this->entityMapping = $entity_mapping;
      }

      public static function getSubscribedEvents() {
        return [Events::LOCAL_ENTITY_MAPPING => 'callback'];
      }

      public function callback(LocalEntityMappingEvent $event) {
        $event->setEntityMapping($this->entityMapping);
      }
    };
  }

}
