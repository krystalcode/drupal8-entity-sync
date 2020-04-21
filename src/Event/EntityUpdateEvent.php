<?php

namespace Drupal\entity_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that gets dispatched when an entity is synced from a remote service.
 *
 * Allows modules to interact during the entity update process.
 */
class EntityUpdateEvent extends Event {

  const EVENT_NAME = 'entity_sync.sync_from.entity_update';

  /**
   * The remote entity that need to be synced.
   *
   * @var object
   */
  protected $remoteEntity;

  /**
   * Constructs the EntityUpdateEvent object.
   *
   * @param object $remote_entity
   *   The remote entity that need to be synced.
   */
  public function __construct($remote_entity) {
    $this->remoteEntity = $remote_entity;
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

}
