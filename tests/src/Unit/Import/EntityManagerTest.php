<?php

namespace Drupal\Tests\entity_sync\Unit\Import;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Client\ClientInterface;
use Drupal\entity_sync\Exception\FieldImportException;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent;
use Drupal\entity_sync\Import\Event\ListFiltersEvent;
use Drupal\entity_sync\Import\Manager;
use Drupal\entity_sync\Import\ManagerInterface;
use Drupal\entity_sync\Import\FieldManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\entity_sync\TestTrait\DataProviderTrait;
use Drupal\Tests\entity_sync\TestTrait\FixturesTrait;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Prophecy\Argument;

require_once __DIR__ . '/../../TestTrait/DataProviderTrait.php';
require_once __DIR__ . '/../../TestTrait/FixturesTrait.php';
require_once __DIR__ . '/../../Exception/TestRuntimeException.php';

/**
 * @coversDefaultClass \Drupal\entity_sync\Import\Manager
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class EntityManagerTest extends UnitTestCase {

  use DataProviderTrait;
  use FixturesTrait;

  /**
   * Data provider; prepares all combinations of data from other providers.
   */
  public function dataProvider() {
    $providers = [
      'syncCaseDataProvider',
      'filterDataProvider',
      'optionDataProvider',
      'eventFilterDataProvider',
      'eventEntityMappingDataProvider',
      'remoteEntityDataProvider',
      'localEntityDataProvider',
      'fieldImportSuccessDataProvider',
    ];

    return $this->combineDataProviders($providers);
  }

  /**
   * This is the main test function. It is provided with all possible
   * combinations of data and configuration so that we test all possibilities
   * that would otherwise be extremely difficul to structure and test.
   *
   * From within this main test function, we start a tree of branching methods
   * in order to test the different scenarios of what happens for each data
   * combination. All branching functions start with the `branch` prefix.
   *
   * @covers ::importRemoteList
   * @dataProvider dataProvider
   */
  public function testImportRemoteList(
    $sync_case,
    array $filters,
    array $options,
    $event_filters,
    $event_entity_mapping,
    $remote_entities,
    $local_entity_class,
    $field_import_success
  ) {
    $remote_entities_count = $remote_entities['count'];
    $remote_entities = $remote_entities['entities'];

    // Mock services required for instantiating the import manager.
    $client_factory = $this->prophesize(ClientFactory::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $field_manager = $this->prophesize(FieldManagerInterface::class);

    $event_dispatcher = $this->buildEventDispatcher(
      $event_filters,
      $event_entity_mapping
    );

    // Mock the synchronization configuration.
    $operation_status = $this->getFixtureDataProperty(
      'operations.import_list.status',
      $sync_case
    );
    $sync = $this->prophesizeSync($operation_status);
    $config_factory = $this->prophesizeConfigFactory($sync_case, $sync);

    // Mock the local entity if it is not NULL.
    // For some reason when the class is prophesized within the data provider
    // the assertions fail to register. We therefore provide the class (config
    // or content entity) and we prophesize here.
    $local_entity = $local_entity_class;
    if ($local_entity) {
      $local_entity = $this->prophesize($local_entity_class);
    }

    // Prepare the test case context as an array so we can easily pass it around
    // to branching methods.
    $test_context = [
      'client_factory' => $client_factory,
      'config_factory' => $config_factory,
      'entity_type_manager' => $entity_type_manager,
      'field_manager' => $field_manager,
      'logger' => $logger,
      'sync' => $sync,
      'sync_case' => $sync_case,
      'filters' => $filters,
      'options' => $options,
      'event_filters' => $event_filters,
      'event_entity_mapping' => $event_entity_mapping,
      'remote_entities' => $remote_entities,
      'remote_entities_count' => $remote_entities_count,
      'local_entity' => $local_entity,
      'field_import_success' => $field_import_success,
    ];

    // Start branching so that we register any additional expectations on the
    // mock objects depending on the scenario provided by the current data
    // combination (test context).
    $this->branchSupportedOperation($test_context, $operation_status);
    $this->branchUnsupportedOperation($test_context, $operation_status);

    // Run!
    $manager = new Manager(
      $client_factory->reveal(),
      $config_factory->reveal(),
      $entity_type_manager->reveal(),
      $event_dispatcher,
      $field_manager->reveal(),
      $logger->reveal()
    );
    $manager->importRemoteList(
      $this->getSyncId($sync_case),
      $filters,
      $options
    );
  }

  /**
   * Data providers.
   */

  /**
   * Data provider for the sync configurations.
   */
  private function syncCaseDataProvider() {
    return [
      // Complete and valid configuration.
      'complete',
      // Import list operation disabled.
      'operations_disabled',
    ];
  }

  /**
   * Data provider for the filters initially passed to the import manager.
   */
  private function filterDataProvider() {
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
   *
   * @I Add tests for invalid options
   *    type     : task
   *    priority : normal
   *    labels   : testing
   * @I Test that the context is passed to the event subscribers
   *    type     : task
   *    priority : normal
   *    labels   : testing
   */
  private function optionDataProvider() {
    return [
      // No options.
      [],
      // Options containing client options. We will just be testing whether the
      // client options are passed to the client so it doesn't matter what they
      // are.
      [
        'client' => ['parameters' => ['key1' => 'value1', 'key2' => 'value2']],
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
  private function eventFilterDataProvider() {
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
  private function eventEntityMappingDataProvider() {
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
      // Create action.
      // Entity that is not bundleable.
      [
        'action' => ManagerInterface::ACTION_CREATE,
        'entity_type_id' => 'user',
      ],
      // Entity that is bundleable and a bundle is provided.
      [
        'action' => ManagerInterface::ACTION_CREATE,
        'entity_type_id' => 'node',
        'entity_bundle' => 'article',
      ],
      // Entity that is bundleable and a bundle is NOT provided.
      [
        'action' => ManagerInterface::ACTION_CREATE,
        'entity_type_id' => 'node',
      ],
      // Update action.
      [
        'action' => ManagerInterface::ACTION_UPDATE,
        'entity_type_id' => 'user',
        'id' => 1,
      ],
    ];
  }

  /**
   * Data provider for the remote entities returned by the client.
   *
   * @I Add tests for invalid remote entities e.g. no iterator
   *    type     : task
   *    priority : normal
   *    labels   : testing
   * @I Add tests for multiple remote entities
   *    type     : task
   *    priority : high
   *    labels   : testing
   */
  private function remoteEntityDataProvider() {
    // None of the entity fields are actually handled by the import entity
    // manager; they are handled by the import field manager instead. We
    // therefore don't have to test here combinations of remote entity
    // fields. Rather, we just need to test basic cases such as no remote
    // entities returned, entities returned as objects or not, entities returned
    // in pages or as a flat list etc.
    //
    return [
      // Empty iterator.
      [
        'count' => 0,
        'entities' => new \ArrayIterator([]),
      ],
      // One result.
      [
        'count' => 1,
        'entities' => new \ArrayIterator([(object) ['userId' => 1]]),
      ],
      // Empty double iterator (paging).
      [
        'count' => 0,
        'entities' => new \ArrayIterator([
          new \ArrayIterator([]),
          new \ArrayIterator([]),
        ]),
      ],
    ];
  }

  /**
   * Data provider for the local entity loaded for the update action.
   *
   * We don't create a data provider for the create action because we would
   * always get a new entity from the entity storage.
   */
  private function localEntityDataProvider() {
    return [
      // No existing entity.
      NULL,
      // Existing content entity.
      ContentEntityInterface::class,
    ];
  }

  /**
   * Data provider for the success of the entity field import.
   */
  private function fieldImportSuccessDataProvider() {
    return [
      // The field import was successful.
      TRUE,
      // An error occurred while importing the entity fields.
      FALSE,
    ];
  }

  /**
   * Branching methods.
   */

  /**
   * The operation is unsupported (disabled in the sync configuration).
   */
  private function branchUnsupportedOperation(
    array $test_context,
    $operation_status
  ) {
    if ($operation_status) {
      return;
    }

    // We expect to log an error and to NOT proceed to calling the client and
    // fetch the entities from the remote.
    $test_context['logger']
      ->error(Argument::any())
      ->shouldBeCalledTimes(1);
    $test_context['client_factory']
      ->get(Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * The operation is supported (enabled in the sync configuration).
   */
  private function branchSupportedOperation(
    array $test_context,
    $operation_status
  ) {
    if (!$operation_status) {
      return;
    }

    // We expect to call the client and fetch the entities from the remote.
    $client = $this->prophesize(ClientInterface::class);
    $client
      ->importList(
        $test_context['event_filters'],
        $test_context['options']['client'] ?? []
      )
      ->willReturn($test_context['remote_entities'])
      ->shouldBeCalledTimes(1);
    $test_context['client_factory']
      ->get($this->getSyncId($test_context['sync_case']))
      ->willReturn($client->reveal())
      ->shouldBeCalledTimes(1);

    $this->branchNoRemoteEntities($test_context);
    $this->branchRemoteEntities($test_context);
  }

  /**
   * No entities were returned from the remote.
   */
  private function branchNoRemoteEntities(array $test_context) {
    if ($test_context['remote_entities_count'] !== 0) {
      return;
    }

    // We cannot test the full path if we don't have entities, since we may not
    // proceed because of an empty entity mapping, but at least we can make sure
    // we don't reach the `create` or `update` methods by testing that the
    // storage is never called for creating or loading an entity.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * Some entities were returned from the remote.
   */
  private function branchRemoteEntities(array $test_context) {
    if ($test_context['remote_entities'] === 0) {
      return;
    }

    // Branch off for each entity; each entity may represent a different
    // scenario e.g. valid entity, invalid entity etc.
    foreach ($test_context['remote_entities'] as $page) {
      if ($page instanceof \Iterator) {
        foreach ($page as $remote_entity) {
          $test_context['remote_entity'] = $remote_entity;
          $this->branchRemoteEntity($test_context);
        }
      }
      else {
        $test_context['remote_entity'] = $page;
        $this->branchRemoteEntity($test_context);
      }
    }

    // @I Test that the terminate event is triggered
    //    type     : task
    //    priority : normal
    //    labels   : testing
  }

  /**
   * Create or update a remote entity.
   */
  private function branchRemoteEntity(array $test_context) {
    $this->branchEmptyEntityMapping($test_context);
    $this->branchSkipAction($test_context);
    $this->branchUnsupportedAction($test_context);
    $this->branchCreate($test_context);
    $this->branchUpdate($test_context);
  }

  /**
   * The final entity mapping is empty.
   */
  private function branchEmptyEntityMapping(array $test_context) {
    if ($test_context['event_entity_mapping']) {
      return;
    }

    // We expect to not proceed to the create/update methods and the storage
    // should therefore never be called for creating or loading an entity.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * The entity mapping requests to skip the entity.
   */
  private function branchSkipAction(array $test_context) {
    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping) {
      return;
    }

    if ($mapping && $mapping['action'] !== ManagerInterface::ACTION_SKIP) {
      return;
    }

    // We expect to not proceed to the create/update methods and the storage
    // should therefore never be called for creating or loading an entity.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * The entity mapping requests an unsupported action.
   */
  private function branchUnsupportedAction(array $test_context) {
    $supported_actions = [
      ManagerInterface::ACTION_SKIP,
      ManagerInterface::ACTION_CREATE,
      ManagerInterface::ACTION_UPDATE,
    ];

    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping || in_array($mapping['action'], $supported_actions)) {
      return;
    }

    // We expect to not proceed to the create/update methods and the storage
    // should therefore never be called for creating or loading an entity.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();

    // Unsupported actions will result in an exception; however, that will be
    // caught for import list operations and an error will be logged instead so
    // that the program can continue to the next entity.
    $this->expectLoggerError($test_context);
  }

  /**
   * The entity mapping requests a new entity to be created.
   */
  private function branchCreate(array $test_context) {
    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping || $mapping['action'] !== ManagerInterface::ACTION_CREATE) {
      return;
    }

    // We expect to check for the `create_entities` configuration option and act
    // accordingly.
    $create_entities = $this->getFixtureDataProperty(
      'operations.import_list.create_entities',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('operations.import_list.create_entities')
      ->willReturn($create_entities)
      ->shouldBeCalledTimes($test_context['remote_entities_count']);

    $this->branchCreateDisabled($test_context, $create_entities);
    $this->branchCreateEnabled($test_context, $create_entities);
  }

  /**
   * Creating new local entities is disabled (in the sync configuration).
   */
  private function branchCreateDisabled(array $test_context, $create_entities) {
    if ($create_entities) {
      return;
    }

    // We expect to never use the storage for creating a new entity; in real
    // world, we could have some entities requesting to be created and some to
    // be updated in which case we would reach the storage for loading entities
    // to be updated. This wouldn't happen in the tests though as the data
    // providers via the test context dictate that either all given entities are
    // to be created or all to be updated. Since handling each remote entity is
    // isolated from the others handling scenarios where some entities are to be
    // created and some to be updated does not really add anything valueable to
    // the tests; it would only increase the complexity of the data providers
    // and the tests and it is acceptable to do things this way.
    $test_context['entity_type_manager']
      ->getStorage(Argument::any())
      ->shouldNotBeCalled();
  }

  /**
   * Creating new local entities is enabled (in the sync configuration).
   */
  private function branchCreateEnabled(array $test_context, $create_entities) {
    if (!$create_entities) {
      return;
    }

    $entity_type = $this->prophesize(EntityTypeInterface::class);

    $this->branchCreateNonBundleableEntityType($test_context, $entity_type);
    $this->branchCreateBundleableEntityType($test_context, $entity_type);

    $test_context['entity_type_manager']
      ->getDefinition($test_context['event_entity_mapping']['entity_type_id'])
      ->willReturn($entity_type->reveal())
      ->shouldBeCalledTimes($test_context['remote_entities_count']);
  }

  /**
   * Create a new local entity of non-bundleable type.
   */
  private function branchCreateNonBundleableEntityType(
    array $test_context,
    $entity_type
  ) {
    // We use `user` as a non-bundleable entity type.
    if ($test_context['event_entity_mapping']['entity_type_id'] !== 'user') {
      return;
    }

    $entity_type
      ->getBundleEntityType()
      ->willReturn(FALSE)
      ->shouldBeCalledTimes($test_context['remote_entities_count']);

    $this->branchCreateSuccess($test_context, []);
  }

  /**
   * Create a new local entity of bundleable type.
   */
  private function branchCreateBundleableEntityType(
    array $test_context,
    $entity_type
  ) {
    // We use `node` as a non-bundleable entity type.
    if ($test_context['event_entity_mapping']['entity_type_id'] !== 'node') {
      return;
    }

    $entity_type
      ->getBundleEntityType()
      ->willReturn(TRUE)
      ->shouldBeCalledTimes($test_context['remote_entities_count']);

    // The entity is bundleable; if the entity mapping has not provided a bundle
    // we cannot create the entity and we expect an exception to be thrown that
    // will be caught and logged.
    if (empty($test_context['event_entity_mapping']['entity_bundle'])) {
      $test_context['entity_type_manager']
        ->getStorage(Argument::any())
        ->shouldNotBeCalled();
      $this->expectLoggerError($test_context);
      return;
    }

    $entity_type
      ->getKey('bundle')
      ->willReturn('type')
      ->shouldBeCalledTimes($test_context['remote_entities_count']);
    $this->branchCreateSuccess(
      $test_context,
      ['type' => $test_context['event_entity_mapping']['entity_bundle']]
    );
  }

  /**
   * Create entity is successful, whether of bundleable of non-bundleable type.
   */
  private function branchCreateSuccess(
    array $test_context,
    array $create_values
  ) {
    // We override the local entity from the data provider that is meant to be
    // for the `update` action only. For the `create` action we always need a
    // new entity created by the storage.
    $local_entity = $this->prophesize(ContentEntityInterface::class);
    $test_context['local_entity'] = $local_entity;

    // For the purposes of the tests, we either create or update all remote
    // entities. We therefore expect the number of times that the `create`
    // method will be called to be equal with the number of remote entities.
    // See comments on the `branchCreateDisabled` method.
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity_storage
      ->create($create_values)
      ->willReturn($local_entity)
      ->shouldBeCalledTimes($test_context['remote_entities_count']);
    $entity_storage
      ->load(Argument::any())
      ->shouldNotBeCalled();

    $test_context['entity_type_manager']
      ->getStorage($test_context['event_entity_mapping']['entity_type_id'])
      ->willReturn($entity_storage->reveal())
      ->shouldBeCalledTimes($test_context['remote_entities_count']);

    $this->branchFieldImportSuccess($test_context);
    $this->branchFieldImportError($test_context);

    // Call `reveal` on the mock entity after we have registered any
    // expectations.
    $local_entity->reveal();
  }

  /**
   * The entity mapping requests a new entity to be updated.
   */
  private function branchUpdate(array $test_context) {
    $mapping = $test_context['event_entity_mapping'];
    if (!$mapping || $mapping['action'] !== ManagerInterface::ACTION_UPDATE) {
      return;
    }

    // For the purposes of the tests, we either create or update all remote
    // entities. We therefore expect the number of times that the `update`
    // method will be called to be equal with the number of remote entities.
    // See comments on the `branchCreateDisabled` method.
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity_storage
      ->load($test_context['event_entity_mapping']['id'])
      ->willReturn($test_context['local_entity'])
      ->shouldBeCalledTimes($test_context['remote_entities_count']);
    $entity_storage
      ->create(Argument::any())
      ->shouldNotBeCalled();

    $test_context['entity_type_manager']
      ->getStorage($test_context['event_entity_mapping']['entity_type_id'])
      ->willReturn($entity_storage->reveal())
      ->shouldBeCalledTimes($test_context['remote_entities_count']);

    $this->branchNonExistingLocalEntity($test_context);
    $this->branchExistingLocalEntity($test_context);
  }

  /**
   * The local entity requested to be updated does not exist.
   */
  private function branchNonExistingLocalEntity(array $test_context) {
    if ($test_context['local_entity']) {
      return;
    }

    // We expect an exception to be thrown that will be caught and logged.
    $this->expectLoggerError($test_context);
  }

  /**
   * The local entity requested to be updated exists.
   */
  private function branchExistingLocalEntity(array $test_context) {
    if (!$test_context['local_entity']) {
      return;
    }

    $this->branchFieldImportSuccess($test_context);
    $this->branchFieldImportError($test_context);

    // Call `reveal` on the mock entity after we have registered any
    // expectations.
    $test_context['local_entity']->reveal();
  }

  /**
   * The field import succeeds.
   */
  private function branchFieldImportSuccess(array $test_context) {
    if (!$test_context['field_import_success']) {
      return;
    }

    // We expect the field manager to run the field import without errors, and
    // all remote entities to be saved.
    $test_context['field_manager']
      ->import(
        $test_context['remote_entity'],
        $test_context['local_entity'],
        $test_context['sync']
      )
      ->shouldBeCalledTimes(1);

    $test_context['local_entity']
      ->save()
      ->shouldBeCalledTimes($test_context['remote_entities_count']);
  }

  /**
   * The field import fails.
   */
  private function branchFieldImportError(array $test_context) {
    if ($test_context['field_import_success']) {
      return;
    }

    // We expect the field manager to run the field import but throwing a
    // `FieldImportException`, and none of the remote entities to be saved.
    $test_context['field_manager']
      ->import(
        $test_context['remote_entity'],
        $test_context['local_entity'],
        $test_context['sync']
      )
      ->willThrow(new FieldImportException('Exception message'))
      ->shouldBeCalledTimes(1);

    $test_context['local_entity']
      ->save()
      ->shouldNotBeCalled();

    $this->expectLoggerError($test_context);
  }

  /**
   * Registers expectations for when we have an error logged.
   *
   * Exceptions are caught and logged as errors instead so that the process can
   * continue to the next entity.
   */
  private function expectLoggerError(array $test_context) {
    $id_field = $this->getFixtureDataProperty(
      'remote_resource.id_field',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('remote_resource.id_field')
      ->willReturn($id_field)
      ->shouldBeCalledAddTimes(1);

    $sync_id = $this->getFixtureDataProperty(
      'id',
      $test_context['sync_case']
    );
    $test_context['sync']
      ->get('id')
      ->willReturn($sync_id)
      ->shouldBeCalledAddTimes(1);

    $test_context['logger']
      ->error(Argument::any())
      ->shouldBeCalledAddTimes(1);
  }

  /**
   * Builds the event subscriber for the `REMOTE_LIST_FILTERS` event.
   */
  private function buildFiltersEventSubscriber($filters) {
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

  /**
   * Builds the event subscriber for the `REMOTE_ENTITY_MAPPING` event.
   */
  private function buildEntityMappingEventSubscriber($entity_mapping) {
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

  /**
   * Prepare the event dispatcher object.
   *
   * This is not a mock object. We register real event subscribers that will be
   * setting the given values.
   */
  private function buildEventDispatcher(
    array $event_filters,
    array $event_entity_mapping
  ) {
    $event_dispatcher = new EventDispatcher();
    $event_dispatcher->addSubscriber(
      $this->buildFiltersEventSubscriber($event_filters)
    );
    $event_dispatcher->addSubscriber(
      $this->buildEntityMappingEventSubscriber($event_entity_mapping)
    );

    return $event_dispatcher;
  }

  /**
   * Prepare the synchronization configuration mock object.
   */
  private function prophesizeSync($operation_status) {
    // In all cases we will be loading the Sync configuration and at the very
    // least checking whether the operation is supported.
    $sync = $this->prophesize(ImmutableConfig::class);
    $sync
      ->get('operations.import_list.status')
      ->willReturn($operation_status)
      ->shouldBeCalledTimes(1);

    return $sync;
  }

  /**
   * Prepare the configuration factory mock object.
   */
  private function prophesizeConfigFactory($sync_case, $sync) {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory
      ->get('entity_sync.sync.' . $this->getSyncId($sync_case))
      ->willReturn($sync)
      ->shouldBeCalledTimes(1);

    return $config_factory;
  }

  /**
   * Gets the ID for the given synchronization mock object.
   */
  private function getSyncId($sync_case) {
    return $this->getFixtureDataProperty('id', $sync_case);
  }

  /**
   * The default fixture ID for the tests in this file.
   */
  private function defaultFixtureId() {
    return 'entity_sync.sync.user';
  }

}
