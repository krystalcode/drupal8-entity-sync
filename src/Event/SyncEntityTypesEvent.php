<?php

namespace Drupal\entity_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Allows modules to define the entities that should be synced to the remote.
 */
class SyncEntityTypesEvent extends Event {

  const EVENT_NAME = 'entity_sync.sync_entity_types';

  /**
   * An associative array of entity types to sync and details about the entity.
   *
   * @var array
   */
  protected $entitiesToSync;

  /**
   * Constructs the object.
   *
   * @param array $entities_to_sync
   *   The entity types to sync.
   */
  public function __construct(array $entities_to_sync) {
    $this->entitiesToSync = $entities_to_sync;
  }

  /**
   * Gets the entities to sync.
   *
   * @return array
   *   The entities to sync.
   */
  public function getEntitiesToSync() {
    return $this->entitiesToSync;
  }

  /**
   * Sets the entities to sync.
   *
   * @param array $entities_to_sync
   *   The entities to sync.
   *
   * @return $this
   */
  public function setEntitiesToSync(array $entities_to_sync) {
    $this->entitiesToSync = $entities_to_sync;
    return $this;
  }

}
