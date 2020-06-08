<?php

namespace Drupal\entity_sync\Import;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\ListFiltersEvent;
use Drupal\entity_sync\Import\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The default import manager.
 */
class Manager implements ManagerInterface {

  /**
   * The client factory.
   *
   * @var \Drupal\entity_sync\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The Entity Sync import field manager.
   *
   * @var \Drupal\entity_sync\Import\FieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new Manager instance.
   *
   * @param \Drupal\entity_sync\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\entity_sync\Import\FieldManagerInterface $field_manager
   *   The Entity Sync import field manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to pass to the client.
   *
   * @I Use \Psr\Log\LoggerInterface
   */
  public function __construct(
    ClientFactory $client_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    FieldManagerInterface $field_manager,
    LoggerChannelInterface $logger
  ) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritDoc}
   */
  public function importRemoteList(
    $sync_id,
    array $filters = [],
    array $options = []
  ) {
    // Load the sync.
    // @I Validate the sync/operation configuration
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    // @I Validate that the provider supports the `import_list` operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);

    // Make sure the operation is enabled and supported by the provider.
    // @I Consider throwing an exception if unsupported operations are run
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, error-handling
    if (!$this->operationSupported($sync, 'import_list')) {
      $this->logger->error(
        sprintf(
          'The synchronization with ID "%s" and/or its provider do not support the `import_list` operation.',
          $sync_id
        )
      );
      return;
    }

    // @I Consider always adding the filters/options to the context
    //    type     : improvement
    //    priority : normal
    //    labels   : context, import, operation
    $context = $options['context'] ?? [];

    // Build the filters for fetching the list of entities.
    $filters = $this->remoteListFilters($filters, $context, $sync);

    // Now, use the remote client to fetch the list of entities.
    $entities = $this->clientFactory->get($sync_id)->importList($filters);
    if (!$entities) {
      return;
    }

    $this->doubleIteratorApply(
      $entities,
      [$this, 'tryCreateOrUpdate'],
      $options['limit'] ?? NULL,
      $sync,
      'import_list'
    );

    // Terminate the operation.
    $this->terminate(
      Events::REMOTE_LIST_TERMINATE,
      'import_list',
      $context,
      $sync
    );
  }

  /**
   * {@inheritDoc}
   */
  public function importLocalEntity(
    $sync_id,
    EntityInterface $local_entity,
    array $options = []
  ) {
    // Load the sync.
    // @I Validate the sync/operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    // @I Validate that the provider supports the `import_entity` operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);

    // Make sure the operation is enabled and supported by the provider.
    if (!$this->operationSupported($sync, 'import_entity')) {
      $this->logger->error(
        sprintf(
          'The synchronization with ID "%s" and/or its provider do not support the `import_entity` operation.',
          $sync_id
        )
      );
      return;
    }

    $context = $options['context'] ?? [];

    // Build the entity mapping for this local entity.
    $entity_mapping = $this->localEntityMapping($local_entity, $sync);
    if (!$entity_mapping) {
      return;
    }

    // Skip importing the remote entity if we are explicitly told to do so.
    if ($entity_mapping['action'] === ManagerInterface::ACTION_SKIP) {
      return;
    }
    elseif ($entity_mapping['action'] !== ManagerInterface::ACTION_IMPORT) {
      throw new \RuntimeException(
        sprintf(
          'Unsupported entity mapping action "%s"',
          $entity_mapping['action']
        )
      );
    }

    // Now, use the remote client to fetch the remote entity for this ID.
    $remote_entity = $this->clientFactory
      ->getByClientConfig($entity_mapping['client'])
      ->importEntity($entity_mapping['entity_id']);

    // Finally, update the entity.
    $this->createOrUpdate($remote_entity, $sync);

    // Terminate the operation.
    // Add to the context the local entity that was imported.
    $this->terminate(
      Events::LOCAL_ENTITY_TERMINATE,
      'import_entity',
      $context + ['local_entity' => $local_entity],
      $sync
    );
  }

  /**
   * Imports the changes without halting execution if an exception is thrown.
   *
   * An error is logged instead; the caller may then continue with import the
   * next entity, if there is one.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param string $operation
   *   The operation that is doing the import; used for logging purposes.
   */
  protected function tryCreateOrUpdate(
    $remote_entity,
    ImmutableConfig $sync,
    $operation
  ) {
    try {
      $this->createOrUpdate($remote_entity, $sync);
    }
    catch (\Exception $e) {
      $id_field = $sync->get('remote_resource.id_field');
      $this->logger->error(
        sprintf(
          'An "%s" exception was thrown while importing the remote entity with ID "%s" as part of the "%s" synchronization and the "%s" operation. The error messages was: %s',
          get_class($e),
          $remote_entity->{$id_field},
          $sync->get('id'),
          $operation,
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Import the changes contained in the given remote entity to a local entity.
   *
   * If an associated local entity is identified, the local entity will be
   * updated. A new local entity will be created otherwise.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  protected function createOrUpdate($remote_entity, ImmutableConfig $sync) {
    // Build the entity mapping for this remote entity.
    // @I Validate the final entity mapping
    //    type     : bug
    //    priority : normal
    //    labels   : mapping, validation
    $entity_mapping = $this->remoteEntityMapping($remote_entity, $sync);

    // If the entity mapping is empty we will not be updating or creating a
    // local entity; nothing to do.
    if (!$entity_mapping) {
      return;
    }

    // Skip updating the local entity if we are explicitly told to do so.
    if ($entity_mapping['action'] === ManagerInterface::ACTION_SKIP) {
      return;
    }
    elseif ($entity_mapping['action'] === ManagerInterface::ACTION_CREATE) {
      $this->create($remote_entity, $sync, $entity_mapping);
    }
    elseif ($entity_mapping['action'] === ManagerInterface::ACTION_UPDATE) {
      $this->update($remote_entity, $sync, $entity_mapping);
    }
    else {
      throw new \RuntimeException(
        sprintf(
          'Unsupported entity mapping action "%s"',
          $entity_mapping['action']
        )
      );
    }
  }

  /**
   * Import the changes from the given remote entity to a new local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $entity_mapping
   *   An associative array containing information about the local entity being
   *   mapped to the given remote entity.
   *   See \Drupal\entity_sync\Event\RemoteEntityMapping::entityMapping.
   *
   * @I Support entity creation validation
   *    type     : bug
   *    priority : normal
   *    labels   : import, validation
   */
  protected function create(
    $remote_entity,
    ImmutableConfig $sync,
    array $entity_mapping
  ) {
    // @I Provide defaults for settings not explicitly set
    //    type     : improvement
    //    priority : low
    //    labels   : config
    // @I Consider using the PHP toggle to switch operations/feature on/off
    //    type     : task
    //    priority : low
    //    labels   : config
    if (!$sync->get('operations.import_list.create_entities')) {
      return;
    }

    // @I Support creation of local entities of types that do not have bundles
    //    type     : bug
    //    priority : normal
    //    labels   : import
    // @I Load the bundle property from the entity keys
    //    type     : bug
    //    priority : normal
    //    labels   : import
    $local_entity = $this->entityTypeManager
      ->getStorage($entity_mapping['entity_type_id'])
      ->create([
        'type' => $entity_mapping['entity_bundle'],
      ]);

    $this->doImportEntity($remote_entity, $local_entity, $sync);
  }

  /**
   * Import the changes from the given remote entity to the local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $entity_mapping
   *   An associative array containing information about the local entity being
   *   mapped to the given remote entity.
   *   See \Drupal\entity_sync\Event\RemoteEntityMapping::entityMapping.
   *
   * @I Support entity update validation
   *    type     : bug
   *    priority : normal
   *    labels   : import, validation
   * @I Check if the changes have already been imported
   *    type     : improvement
   *    priority : normal
   *    labels   : import, validation
   */
  protected function update(
    $remote_entity,
    ImmutableConfig $sync,
    array $entity_mapping
  ) {
    // Load the local entity that this remote entity is associated with.
    // @I Validate that the local entity is of the expected bundle
    //    type     : task
    //    priority : low
    //    labels   : import, validation
    //    notes    : The synchronization configuration should allow bypassing
    //               bundle validation.
    $local_entity = $this->entityTypeManager
      ->getStorage($entity_mapping['entity_type_id'])
      ->load($entity_mapping['id']);

    if (!$local_entity) {
      // @I Add more details about the remote entity in the exception message
      //    type     : task
      //    priority : low
      //    labels   : error-handling, import
      throw new \RuntimeException(
        sprintf(
          'A non-existing local entity of type "%s" and ID "%s" was requested to be mapped to a remote entity.',
          $entity_mapping['type_id'],
          $local_entity->id()
        )
      );
    }

    $this->doImportEntity($remote_entity, $local_entity, $sync);
  }

  /**
   * Performs the actual import of a remote entity to a local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The associated local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  protected function doImportEntity(
    $remote_entity,
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    // @I Pass context to the field manager
    //    type     : improvement
    //    priority : normal
    //    labels   : field, import
    $this->fieldManager->import($remote_entity, $local_entity, $sync);

    // If no errors occurred (the field manager would throw an exception),
    // proceed with saving the entity.
    $local_entity->save();
  }

  /**
   * Builds and returns the entity mapping for the given remote entity.
   *
   * The entity mapping defines if and which local entity will be updated with
   * the data contained in the given remote entity. The default mapping
   * identifies the local entity based on an entity field containing the remote
   * entity's ID.
   *
   * An event is dispatched that allows subscribers to map the remote entity to
   * a different local entity, or to decide to not import it at all.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final entity mapping.
   */
  protected function remoteEntityMapping(
    $remote_entity,
    ImmutableConfig $sync
  ) {
    $event = new RemoteEntityMappingEvent($remote_entity, $sync);
    $this->eventDispatcher->dispatch(Events::REMOTE_ENTITY_MAPPING, $event);

    // Return the final mapping.
    return $event->getEntityMapping();
  }

  /**
   * Builds and returns the remote ID for the given local entity.
   *
   * The local entity mapping defines if and which remote entity will be
   * imported for the given local entity. The default mapping identifies the
   * remote entity based on a local entity field containing the remote
   * entity's ID.
   *
   * An event is dispatched that allows subscribers to map the local entity to a
   * different remote entity, or to decide to not import it at all.
   *
   * @param \Drupal\core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final entity mapping.
   */
  protected function localEntityMapping(
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $event = new LocalEntityMappingEvent(
      $local_entity,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::LOCAL_ENTITY_MAPPING, $event);

    // Return the final mapping.
    return $event->getEntityMapping();
  }

  /**
   * Builds and returns the filters for importing a remote list of entities.
   *
   * An event is dispatched that allows subscribers to alter the filters that
   * determine which entities will be fetched from the remote resource.
   *
   * @param array $filters
   *   The current filters.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final filters.
   */
  protected function remoteListFilters(
    array $filters,
    array $context,
    ImmutableConfig $sync
  ) {
    $event = new ListFiltersEvent(
      $filters,
      $context,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::REMOTE_LIST_FILTERS, $event);

    // Return the final filters.
    return $event->getFilters();
  }

  /**
   * Dispatches an event when an operation is being terminated.
   *
   * @param string $event_name
   *   The name of the event to dispatch. It must be a name for a
   *   `TerminateOperationEvent` event.
   * @param string $operation
   *   The name of the operation being terminated.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  protected function terminate(
    $event_name,
    $operation,
    array $context,
    ImmutableConfig $sync
  ) {
    $event = new TerminateOperationEvent(
      $operation,
      $context,
      $sync
    );
    $this->eventDispatcher->dispatch($event_name, $event);
  }

  /**
   * Checks that the given operation is enabled for the given synchronization.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for the synchronization that defines the
   *   operation we are currently executing.
   * @param string $operation
   *   The operation to check.
   *
   * @return bool
   *   TRUE if the operation is enabled and supported, FALSE otherwise.
   */
  protected function operationSupported(ImmutableConfig $sync, $operation) {
    // @I Check that the provider supports the corresponding method as well
    //    type     : bug
    //    priority : normal
    //    labels   : import, operation, validation
    if (!$sync->get("operations.$operation.status")) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Apply a callback to all items within an iterator.
   *
   * The callback needs to accept the item as its first argument.
   *
   * If the items of the iterator are iterators themselves, the callback is
   * applied to the items in the inner iterator.
   *
   * This is used to support paging; the outer iterator contains pages and each
   * page is an iterator that contains the items.
   *
   * If a limit is provided, applying the callback will simply stop when we
   * reach the limit; otherwise, all items contained in the iterator(s) will be
   * processed.
   *
   * @I Review and implement logging strategy for `info` and `debug` levels
   *    type     : feature
   *    priority : normal
   *    labels   : logging
   *
   * @param \Iterator $iterator
   *   The iterator that contains the items.
   * @param callable $callback
   *   The callback to apply to the items.
   * @param int $limit
   *   The maximum number of items to apply the callback to, or NULL for no
   *   limit.
   * @param mixed $args
   *   The arguments to pass to the callback after the item.
   */
  protected function doubleIteratorApply(
    \Iterator $iterator,
    callable $callback,
    $limit = NULL,
    ...$args
  ) {
    $counter = 0;

    foreach ($iterator as $items) {
      if ($counter === $limit) {
        break;
      }

      if (!$items instanceof \Iterator) {
        call_user_func_array(
          $callback,
          array_merge([$items], $args)
        );
        $counter++;
        continue;
      }

      foreach ($items as $item) {
        if ($counter === $limit) {
          break 2;
        }

        call_user_func_array(
          $callback,
          array_merge([$item], $args)
        );
        $counter++;
      }
    }
  }

}
