<?php

namespace Drupal\Tests\entity_sync\Unit;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Client\ClientInterface;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent;
use Drupal\entity_sync\Import\Event\ListFiltersEvent;
use Drupal\entity_sync\Import\Event\FieldMappingEvent;
use Drupal\entity_sync\Import\Manager;
use Drupal\entity_sync\Import\ManagerInterface;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Yaml\Yaml;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @coversDefaultClass \Drupal\entity_sync\Import\Manager
 */
class ImportManagerTest extends UnitTestCase {

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
   * Data provider for the filters initially passed to the import manager.
   */
  public function filterDataProvider() {
    return [
      // No filters.
      [],
      // Some filters. We will be testing whether the filters are passed to the
      // client so it doesn't matter what they are.
      [
        'changed_start' => 1001001001,
        'changed_end' => 2002002002,
      ],
    ];
  }

  /**
   * Data provider for the options initially passed to the import manager.
   */
  public function optionDataProvider() {
    return [
      // No options.
      [],
      // Some context. We will be testing whether the context is passed to the
      // events so it doesn't matter what they are.
      [
        'state_manager' => 'entity_sync',
      ],
    ];
  }

  /**
   * Data provider for the filters set via event subscribers.
   *
   * We want to test whether the event that allows altering the filters is
   * triggered and the filters returned after the subscribers are run are the
   * ones passed to the client.
   *
   * @I Add tests for invalid filter data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  public function eventFilterDataProvider() {
    return [
      // No filters.
      [],
      // Some filters. We will be testing whether the filters are passed to the
      // client so it doesn't matter what they are as long as they are different
      // than the ones defined as the initial filters passed to the manager
      // method called.
      [
        'changed_start' => 1010101010,
        'changed_end' => 2020202020,
      ],
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
        'entity_type_id' => 'user',
        'id' => 1,
      ],
      // Invalid action.
      [
        'action' => 'unsupported-action',
        'entity_type_id' => 'user',
        'id' => 1,
      ],
    ];
  }

  /**
   * Data provider for the field mapping set via events subscribers.
   *
   * @I Add tests for invalid field mapping data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  public function eventFieldMappingDataProvider() {
    return [
      // No fields.
      [],
      // Some fields.
      [
        'machine_name' => 'mail',
        'remote_name' => 'email',
      ],
    ];
  }

  /**
   * Data provider for the remote entities returned by the client.
   *
   * @I Add tests for invalid remote entities data
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  public function remoteEntityDataProvider() {
    return [
      // No filters.
      [],
      // Empty iterator.
      new \ArrayIterator([]),
      // One result.
      new \ArrayIterator([(object) ['userId' => 1]]),
    ];
  }

  /**
   * Prepares all possible combinations of data from other providers.
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
      'filterDataProvider',
      'optionDataProvider',
      'eventFilterDataProvider',
      'eventEntityMappingDataProvider',
      'eventFieldMappingDataProvider',
      'remoteEntityDataProvider',
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
   * @test
   * @dataProvider dataProvider
   */
  public function testAll(
    $sync_case,
    array $filters,
    array $options,
    $event_filters,
    $event_entity_mapping,
    $event_field_mapping,
    $remote_entities
  ) {
    // Mock services required for instantiating the import manager.
    $client_factory = $this->prophesize(ClientFactory::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $sync = $this->prophesize(ImmutableConfig::class);

    // Register event subscribers that will be setting the given values.
    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addSubscriber(
      $this->buildFiltersEventSubscriber($event_filters)
    );
    $event_dispatcher->addSubscriber(
      $this->buildEntityMappingEventSubscriber($event_entity_mapping)
    );
    $event_dispatcher->addSubscriber(
      $this->buildFieldMappingEventSubscriber($event_field_mapping)
    );

    // In all cases we will be loading the Sync configuration and at the very
    // least checking whether the operation is supported.
    $operation_status = $this->getSyncProperty(
      $sync_case,
      'operations.import_list.status'
    );
    $sync = $this->prophesize(ImmutableConfig::class);
    $sync
      ->get('operations.import_list.status')
      ->willReturn($operation_status)
      ->shouldBeCalledTimes(1);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
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
      'sync' => $sync,
      'sync_case' => $sync_case,
      'filters' => $filters,
      'options' => $options,
      'event_filters' => $event_filters,
      'event_entity_mapping' => $event_entity_mapping,
      'event_field_mapping' => $event_field_mapping,
      'remote_entities' => $remote_entities,
    ];

    $this->branchSupportedOperation($test_context, $operation_status);
    $this->branchUnsupportedOperation($test_context, $operation_status);

    $manager = new Manager(
      $client_factory->reveal(),
      $config_factory->reveal(),
      $entity_type_manager->reveal(),
      $event_dispatcher,
      $logger->reveal()
    );
    $manager->importRemoteList(self::SYNC_ID, $filters, []);
  }

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
    $test_context['client_factory']
      ->get(Argument::any())
      ->shouldNotBeCalled();
  }

  private function branchSupportedOperation(
    array $test_context,
    $operation_status
  ) {
    if (!$operation_status) {
      return;
    }

    $client = $this->prophesize(ClientInterface::class);
    $client
      ->importList($test_context['event_filters'])
      ->willReturn($test_context['remote_entities']);
    $test_context['client_factory']
      ->get(self::SYNC_ID)
      ->willReturn($client);

    // @I if exceptions are caught by `tryCreateOrUpdate`.
    // @I invalid entity mapping - needs adding validation.

    $this->branchNoRemoteEntities($test_context);
    $this->branchRemoteEntities($test_context);
  }

  private function branchNoRemoteEntities(array $test_context) {
    if (!$this->remoteEntitiesAreEmpty($test_context['remote_entities'])) {
      return;
    }

    // We cannot test the full path if we don't have entities, since we may not
    // proceed because of an empty entity mapping, but at least we can make sure
    // we don't reach the `create` or `update` methods.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  private function branchRemoteEntities(array $test_context) {
    if ($this->remoteEntitiesAreEmpty($test_context['remote_entities'])) {
      return;
    }

    $this->branchEmptyEntityMapping($test_context);
    $this->branchSkipAction($test_context);
    $this->branchUnsupportedAction($test_context);
  }

  private function branchEmptyEntityMapping(array $test_context) {
    if ($test_context['event_entity_mapping']) {
      return;
    }

    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  private function branchSkipAction($test_context) {
    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping) {
      return;
    }

    if ($mapping && $mapping['action'] !== ManagerInterface::ACTION_SKIP) {
      return;
    }

    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  private function branchUnsupportedAction($test_context) {
    $supported_actions = [
      ManagerInterface::ACTION_SKIP,
      ManagerInterface::ACTION_CREATE,
      ManagerInterface::ACTION_UPDATE,
    ];

    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping || in_array($mapping['action'], $supported_actions)) {
      return;
    }

    // Unsupported actions will result in an exception; however, that will be
    // caught for import list operations and an error will be logged instead so
    // that program can continue to the next entity.
    $id_field = $this->getSyncProperty(
      $test_context['sync_case'],
      'remote_resource.id_field'
    );
    $test_context['sync']
      ->get('remote_resource.id_field')
      ->willReturn($id_field)
      ->shouldBeCalledTimes(1);
    $test_context['sync']
      ->get('id')
      ->shouldBeCalledTimes(1);

    // @I Expect the logger to be called the correct number of times
    $test_context['logger']
      ->error(Argument::any())
      ->shouldBeCalled();
  }

  private function remoteEntitiesAreEmpty($remote_entities) {
    if (!$remote_entities) {
      return TRUE;
    }

    // If the entities are not contained in an iterator we consider that we
    // don't have entities. Validation should be handled in other tests.
    if (!is_object($remote_entities)) {
      return TRUE;
    }
    if (!$remote_entities instanceof \Iterator) {
      return TRUE;
    }

    // If the iterator is empty, we don't have entities.
    if ($remote_entities->count() === 0) {
      return TRUE;
    }

    // If the iterator is not empty and we don't have pagination (double
    // iterator), then we do have entities.
    foreach ($remote_entities as $remote_entity) {
      if ($remote_entity instanceof \Iterator) {
        break;
      }

      return FALSE;
    }

    // Otherwise, we have pagination. We do have entities if at least one page
    // has at least one item.
    foreach ($remote_entities as $page) {
      if ($page->count() !== 0) {
        return FALSE;
      }

      return TRUE;
    }
  }

  protected function getSyncProperty($sync_case, $key = '') {
    $data = $this->getSync($sync_case);

    if (empty($key)) {
      return $data;
    }
    else {
      $parts = explode('.', $key);
      if (count($parts) == 1) {
        return isset($data[$key]) ? $data[$key] : NULL;
      }
      else {
        $value = NestedArray::getValue($data, $parts, $key_exists);
        return $key_exists ? $value : NULL;
      }
    }
  }

  protected function getSync($sync_case) {
    $syncs = Yaml::parse(
      file_get_contents(realpath(__DIR__ . '/../../fixtures/' . self::FIXTURES_FILENAME))
    );

    if (isset($syncs[$sync_case])) {
      return $syncs[$sync_case];
    }

    throw new \InvalidArgumentException(
      sprintf(
        'Synchronization case "%s" not found in the fixtures contain in "%s"',
        $sync_case,
        self::FIXTURES_FILENAME
      )
    );
  }

  public function buildFiltersEventSubscriber($filters) {
    return new class($filters) implements EventSubscriberInterface {
      private $filters;

      public function __construct(array $filters) {
        $this->filters = $filters;
      }

      public static function getSubscribedEvents() {
        return [Events::REMOTE_LIST_FILTERS => 'callback'];
      }

      public function callback(ListFiltersEvent $event) {
        $event->setFilters($this->filters);
      }
    };
  }

  public function buildEntityMappingEventSubscriber($entity_mapping) {
    return new class($entity_mapping) implements EventSubscriberInterface {
      private $entityMapping;

      public function __construct(array $entity_mapping) {
        $this->entityMapping = $entity_mapping;
      }

      public static function getSubscribedEvents() {
        return [Events::REMOTE_ENTITY_MAPPING => 'callback'];
      }

      public function callback(RemoteEntityMappingEvent $event) {
        $event->setEntityMapping($this->entityMapping);
      }
    };
  }

  public function buildFieldMappingEventSubscriber($field_mapping) {
    return new class($field_mapping) implements EventSubscriberInterface {
      private $fieldMapping;

      public function __construct(array $field_mapping) {
        $this->fieldMapping = $field_mapping;
      }

      public static function getSubscribedEvents() {
        return [Events::FIELD_MAPPING => 'callback'];
      }

      public function callback(FieldMappingEvent $event) {
        $event->setFieldMapping($this->fieldMapping);
      }
    };
  }

}
