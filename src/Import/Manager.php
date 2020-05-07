<?php

namespace Drupal\entity_sync\Import;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Import\Event\EntityMappingEvent;
use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\FieldMappingEvent;
use Drupal\entity_sync\Import\Event\RemoteIdMappingEvent;

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
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to pass to the client.
   */
  public function __construct(
    ClientFactory $client_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    LoggerChannelInterface $logger
  ) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
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

    // Now, use the remote client to fetch the list of entities.
    $entities = $this->clientFactory->get($sync_id)->importList($filters);
    if (!$entities) {
      return;
    }

    $this->doubleIteratorApply(
      $entities,
      [$this, 'tryCreateOrUpdate'],
      $sync,
      'import_list'
    );
  }

  /**
   * {@inheritDoc}
   */
  public function importLocalEntity(
    EntityInterface $local_entity,
    $sync_id,
    array $options = []
  ) {
    // Load the sync.
    // @I Validate the sync/operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);

    // Build the remote ID mapping for this local entity.
    $remote_id = $this->remoteIdMapping($local_entity, $sync);

    // Now, use the remote client to fetch the remote entity for this ID.
    $remote_entity = $this
      ->clientFactory
      ->get($sync_id)
      ->importEntity($remote_id);
    if (!$remote_entity) {
      return;
    }

    // Finally, update the entity.
    $this->createOrUpdate($remote_entity, $sync);
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
          'An error occur while importing the remote entity with ID "%s" as part of the "%s" synchronization and the "%s" operation. The error messages was: %s',
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
    $entity_mapping = $this->entityMapping($remote_entity, $sync);

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
   *   See \Drupal\entity_sync\Event\EntityMapping::entityMapping.
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
   *   See \Drupal\entity_sync\Event\EntityMapping::entityMapping.
   *
   * @I Support entity update validation
   *    type     : bug
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
    // Build the field mapping for the fields that will be imported.
    // @I Validate the final field mapping
    //    type     : bug
    //    priority : normal
    //    labels   : mapping, validation
    $field_mapping = $this->fieldMapping($remote_entity, $local_entity, $sync);

    // If the field mapping is empty we will not be updating any fields in the
    // local entity; nothing to do.
    if (!$field_mapping) {
      return;
    }

    foreach ($field_mapping as $field_info) {
      $this->doImportField($remote_entity, $local_entity, $field_info);
    }

    // Update the remote ID field.
    // @I Support not updating the remote ID field
    //    type     : bug
    //    priority : low
    //    labels   : import
    // @I Move updating remote ID and remote changed fields to separate methods
    //    type     : task
    //    priority : low
    //    labels   : refactoring
    $local_id_field = $sync->get('entity.remote_id_field');
    if ($local_entity->hasField($local_id_field)) {
      $remote_id_field = $sync->get('remote_resource.id_field');
      $local_entity->set(
        $local_id_field,
        $remote_entity->{$remote_id_field}
      );
    }

    // Update the remote changed field. The remote changed field will be used in
    // `hook_entity_insert` to prevent triggering an export of the local entity.
    // @I Support non-Unix timestamp formats for the remote changed field
    //    type     : bug
    //    priority : normal
    //    labels   : import
    $local_changed_field = $sync->get('entity.remote_changed_field');
    if ($local_entity->hasField($local_changed_field)) {
      $remote_changed_field = $sync->get('remote_resource.changed_field');
      $local_entity->set(
        $local_changed_field,
        $remote_entity->{$remote_changed_field}
      );
    }

    $local_entity->save();
  }

  /**
   * Performs the actual import of a remote field to a local field.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The associated local entity.
   * @param array $field_info
   *   The field info.
   *   See \Drupal\entity_sync\Event\FieldMapping::fieldMapping.
   */
  protected function doImportField(
    $remote_entity,
    EntityInterface $local_entity,
    array $field_info
  ) {
    // If the field value should be converted and stored by a custom callback,
    // then invoke that.
    if (isset($field_info['callback'])) {
      call_user_func(
        $field_info['callback'],
        $remote_entity,
        $local_entity,
        $field_info
      );
    }
    // Else, we assume direct copy of the remote field value into the local
    // field.
    // @I Add more details about the field mapping in the exception message
    //    type     : task
    //    priority : low
    //    labels   : error-handling, import
    // @I Handle fields like the 'status' field
    //    type     : bug
    //    priority : normal
    //    labels   : error-handling, import
    elseif (!$local_entity->hasField($field_info['name'])) {
      throw new \RuntimeException(
        sprintf(
          'The non-existing local entity field "%s" was requested to be mapped to a remote field',
          $field_info['name']
        )
      );
    }
    else {
      $local_entity->set(
        $field_info['name'],
        $remote_entity->{$field_info['remote_name']}
      );
    }
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
  protected function entityMapping($remote_entity, ImmutableConfig $sync) {
    $event = new EntityMappingEvent($remote_entity, $sync);
    $this->eventDispatcher->dispatch(Events::ENTITY_MAPPING, $event);

    // Return the final mapping.
    return $event->getEntityMapping();
  }

  /**
   * Builds and returns the field mapping for the given entities.
   *
   * The field mapping defines which local entity fields will be updated with
   * which values contained in the given remote entity. The default mapping is
   * defined in the synchronization to which the operation we are currently
   * executing belongs.
   *
   * An event is dispatched that allows subscribers to alter the default field
   * mapping.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final field mapping.
   */
  protected function fieldMapping(
    $remote_entity,
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $event = new FieldMappingEvent(
      $remote_entity,
      $local_entity,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::FIELD_MAPPING, $event);

    // Return the final mappings.
    return $event->getFieldMapping();
  }

  /**
   * Builds and returns the remote ID for the given local entity.
   *
   * The remote ID mapping defines which remote ID to use to fetch the remote
   * entity. The default remote ID field used to fetch the value is defined in
   * the synchronization to which the operation we are currently executing
   * belongs.
   *
   * An event is dispatched that allows subscribers to alter the default remote
   * ID mapping.
   *
   * @param \Drupal\core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final field mapping.
   */
  protected function remoteIdMapping(
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $event = new RemoteIdMappingEvent(
      $local_entity,
      $sync
    );
    $this->eventDispatcher->dispatch(Events::REMOTE_ID_MAPPING, $event);

    // Return the remote ID.
    return $event->getRemoteId();
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
   * @param \Iterator $iterator
   *   The iterator that contains the items.
   * @param callable $callback
   *   The callback to apply to the items.
   * @param mixed $args
   *   The arguments to pass to the callback after the item.
   */
  protected function doubleIteratorApply(
    \Iterator $iterator,
    callable $callback,
    ...$args
  ) {
    foreach ($iterator as $items) {
      if (!$items instanceof \Iterator) {
        call_user_func_array(
          $callback,
          array_merge([$items], $args)
        );
        continue;
      }

      foreach ($items as $item) {
        call_user_func_array(
          $callback,
          array_merge([$item], $args)
        );
      }
    }
  }

}
