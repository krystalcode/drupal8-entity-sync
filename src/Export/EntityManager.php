<?php

namespace Drupal\entity_sync\Export;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\EntityManagerBase;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The default export manager.
 */
class EntityManager extends EntityManagerBase implements EntityManagerInterface {

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
   * The Entity Sync export field manager.
   *
   * @var \Drupal\entity_sync\Export\FieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new EntityManager instance.
   *
   * @param \Drupal\entity_sync\Client\ClientFactory $client_factory
   *   The client factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\entity_sync\Export\FieldManagerInterface $field_manager
   *   The Entity Sync export field manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ClientFactory $client_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    FieldManagerInterface $field_manager,
    LoggerInterface $logger
  ) {
    $this->clientFactory = $client_factory;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->fieldManager = $field_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public function exportLocalEntity(
    $sync_id,
    ContentEntityInterface $local_entity,
    array $options = []
  ) {
    // Load the sync.
    // @I Validate the sync/operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    // @I Validate that the provider supports the `export_entity` operation
    //    type     : bug
    //    priority : normal
    //    labels   : operation, sync, validation
    //    notes    : Review whether the validation should happen upon runtime
    //               i.e. here, or when the configuration is created/imported.
    // @I Validate that the given local entity is of the correct type
    //    type     : bug
    //    priority : normal
    //    labels   : export, operation, sync, validation
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

    $context = $options['context'] ?? [];

    // Export the entity.
    $data = $this->createOrUpdate($local_entity, $sync);

    // Terminate the operation.
    // Add to the context the local entity that was exported.
    $this->terminate(
      Events::LOCAL_ENTITY_TERMINATE,
      'export_entity',
      $context + ['local_entity' => $local_entity],
      $sync,
      $data ?? []
    );
  }

  /**
   * Export the changes contained in the given local entity to a remote entity.
   *
   * If an associated remote entity is identified, the remote entity will be
   * updated. A new remote entity will be created otherwise.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  protected function createOrUpdate(
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    // Build the entity mapping for this local entity.
    $entity_mapping = $this->localEntityMapping($local_entity, $sync);

    // If the entity mapping is empty we will not be updating or creating a
    // remote entity; nothing to do.
    if (!$entity_mapping) {
      return;
    }

    $data = ['action' => $entity_mapping['action']];

    // Skip exporting the local entity if we are explicitly told to do so.
    if ($entity_mapping['action'] === EntityManagerInterface::ACTION_SKIP) {
      return $data;
    }
    elseif ($entity_mapping['action'] === EntityManagerInterface::ACTION_CREATE) {
      $data['response'] = $this->create($local_entity, $sync, $entity_mapping);
      return $data;
    }
    elseif ($entity_mapping['action'] === EntityManagerInterface::ACTION_UPDATE) {
      $data['response'] = $this->update($local_entity, $sync, $entity_mapping);
      return $data;
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
   * Export the changes from the given local entity to a new remote entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $entity_mapping
   *   An associative array containing information about the remote entity being
   *   mapped to the given local entity.
   *   See \Drupal\entity_sync\Export\Event\LocalEntityMapping::entityMapping.
   *
   * @return mixed|null
   *   The response from the client, or NULL if the operation was not run.
   */
  protected function create(
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    array $entity_mapping
  ) {
    if (!$sync->get('operations.export_entity.create_entities')) {
      return;
    }

    // Prepare the fields to create on the remote object.
    // @I Pass context to the field manager
    //    type     : improvement
    //    priority : normal
    //    labels   : field, export
    $remote_fields = $this->fieldManager->export(
      $local_entity,
      NULL,
      $sync
    );

    // Do the export i.e. call the client.
    return $this->clientFactory
      ->getByClientConfig($entity_mapping['client'])
      ->create($remote_fields);
  }

  /**
   * Export the changes from the given local entity to the remote entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $entity_mapping
   *   An associative array containing information about the remote entity being
   *   mapped to the given local entity.
   *   See \Drupal\entity_sync\Export\Event\LocalEntityMapping::entityMapping.
   *
   * @return mixed|null
   *   The response from the client, or NULL if the operation was not run.
   *
   * @I Support entity update validation
   *    type     : bug
   *    priority : normal
   *    labels   : export, validation
   * @I Check if the changes have already been exported
   *    type     : improvement
   *    priority : normal
   *    labels   : export, validation
   */
  protected function update(
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    array $entity_mapping
  ) {
    if (!$sync->get('operations.export_entity.update_entities')) {
      return;
    }

    // Prepare the fields to update on the remote object.
    // @I Pass context to the field manager
    //    type     : improvement
    //    priority : normal
    //    labels   : field, export
    $remote_fields = $this->fieldManager->export(
      $local_entity,
      $entity_mapping['id'],
      $sync
    );

    // Do the export i.e. call the client.
    return $this->clientFactory
      ->getByClientConfig($entity_mapping['client'])
      ->update($entity_mapping['id'], $remote_fields);
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
   * @param \Drupal\core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   *
   * @return array
   *   The final entity mapping.
   */
  protected function localEntityMapping(
    ContentEntityInterface $local_entity,
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

}
