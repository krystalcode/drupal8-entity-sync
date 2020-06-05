<?php

namespace Drupal\entity_sync\Export;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\Export\Event\LocalFieldMappingEvent;
use Drupal\entity_sync\SyncManagerBase;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The default export manager.
 */
class Manager extends SyncManagerBase implements ManagerInterface {

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
  public function exportLocalEntity(
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
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);

    // Make sure the operation is enabled and supported by the provider.
    if (!$this->operationSupported($sync, 'export_entity')) {
      $this->logger->error(
        sprintf(
          'The synchronization with ID "%s" and/or its provider do not support the `export_entity` operation.',
          $sync_id
        )
      );
      return;
    }

    // Build the entity mapping for this local entity.
    $entity_mapping = $this->localEntityMapping($local_entity, $sync);
    if (!$entity_mapping) {
      return;
    }

    // Skip exporting the remote entity if we are explicitly told to do so.
    if ($entity_mapping['action'] === ManagerInterface::ACTION_SKIP) {
      return;
    }
    elseif ($entity_mapping['action'] !== ManagerInterface::ACTION_EXPORT) {
      throw new \RuntimeException(
        sprintf(
          'Unsupported entity mapping action "%s".',
          $entity_mapping['action']
        )
      );
    }

    // Build the field maping for this local entity.
    $field_mapping = $this->fieldMapping($local_entity, $sync);
    if (!$field_mapping) {
      return;
    }

    // Now, construct an object with the values to pass to the remote.
    $remote_entity = $this->constructEntityObject(
      $local_entity,
      $field_mapping
    );

    // Now, use the client to export out the entity with values to the remote.
    $response = $this->clientFactory
      ->getByClientConfig($entity_mapping['client'])
      ->exportEntity($remote_entity, $entity_mapping['id']);

    // @TODO: Finally, update the local entity, ie. save the remote ID, etc.

    // @TODO: Terminate the event.
  }

  /**
   * Construct the entity object that we're going to send to the remote.
   *
   * @param \Drupal\core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param array $field_mapping
   *   The field mapping array.
   *
   * @return object
   *   An \stdClass object with the values to pass to the remote.
   */
  public function constructEntityObject(
    EntityInterface $local_entity,
    array $field_mapping
  ) {
    $remote_entity = new \stdClass();

    // Go through all the fields of the local entity and map them to the remote.
    foreach ($field_mapping as $field_info) {
      // If the field value should be converted and stored by a custom callback,
      // then invoke that.
      if (isset($field_info['export_callback'])) {
        call_user_func(
          $field_info['export_callback'],
          $remote_entity,
          $local_entity,
          $field_info
        );
      }
      // Else, we assume direct copy of the local field value into the remote
      // field.
      // @I Add more details about the field mapping in the exception message
      //    type     : task
      //    priority : low
      //    labels   : error-handling, export
      // @I Handle fields like the 'status' field
      //    type     : bug
      //    priority : normal
      //    labels   : error-handling, export
      elseif (!$local_entity->hasField($field_info['machine_name'])) {
        throw new \RuntimeException(
          sprintf(
            'The non-existing local entity field "%s" was requested to be mapped to a remote field.',
            $field_info['machine_name']
          )
        );
      }
      else {
        $remote_entity->{$field_info['remote_name']} = $local_entity->{$field_info['machine_name']}->value;
      }
    }

    return $remote_entity;
  }

  /**
   * Builds and returns the remote ID for the given local entity.
   *
   * The local entity mapping defines if and which remote entity this local
   * entity will be exported to. The default mapping identifies the remote
   * entity based on a local entity field containing the remote entity's ID.
   *
   * An event is dispatched that allows subscribers to map the local entity to a
   * different remote entity, or to decide to not export it at all.
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
    $this->eventDispatcher->dispatch($event, Events::LOCAL_ENTITY_MAPPING);

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
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $event = new LocalFieldMappingEvent(
      $local_entity,
      $sync
    );
    $this->eventDispatcher->dispatch($event, Events::LOCAL_FIELD_MAPPING);

    // Return the final mappings.
    return $event->getFieldMapping();
  }

}
