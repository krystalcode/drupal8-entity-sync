<?php

namespace Drupal\entity_sync\Import\Event;

use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the import entity mapping event.
 *
 * Allows subscribers to define which local entity a remote entity should be
 * mapped to.
 */
class EntityMappingEvent extends Event {

  /**
   * The entity mapping for the remote entity being imported.
   *
   * The mapping is an associative array that defines which local entity the
   * remote entity should be mapped to. Supported array elements:
   * - action: The action to be taken. Possible values are:
   *   - ManagerInterface::ACTION_SKIP: Do not import the entity.
   *   - ManagerInterface::ACTION_CREATE: Create a new local entity.
   *   - ManagerInterface::ACTION_UPDATE: Update an existing local entity.
   *   See \Drupal\entity_sync\Import\ManagerInterface.
   * - type_id: The type ID of the entity that will be created or updated.
   * - bundle: The bundle of the entity that will be created or updated.
   * - id: The ID of the entity that will be created or updated.
   *
   * @var array
   */
  protected $entityMapping = [];

  /**
   * The remote entity being imported.
   *
   * @var object
   */
  protected $remoteEntity;

  /**
   * The synchronization configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sync;

  /**
   * Constructs a new EntityMappingEvent object.
   *
   * @param object $remote_entity
   *   The remote entity that's about to be synced.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function __construct($remote_entity, ImmutableConfig $sync) {
    $this->remoteEntity = $remote_entity;
    $this->sync = $sync;
  }

  /**
   * Gets the entity mapping array.
   *
   * @return array
   *   The entity mapping array.
   */
  public function getEntityMapping() {
    return $this->entityMapping;
  }

  /**
   * Sets the entity mapping.
   *
   * @param array $entity_mapping
   *   The entity mapping array.
   */
  public function setEntityMapping(array $entity_mapping) {
    $this->entityMapping = $entity_mapping;
  }

  /**
   * Gets the remote entity.
   *
   * @return object
   *   The remote entity.
   */
  public function getRemoteEntity() {
    return $this->remoteEntity;
  }

  /**
   * Gets the synchronizatio configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The synchronization configuration object.
   */
  public function getSync() {
    return $this->sync;
  }

}
