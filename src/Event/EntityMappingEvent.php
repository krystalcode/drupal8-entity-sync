<?php

namespace Drupal\entity_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that gets dispatched when an entity is about to be synced.
 *
 * Allows modules to define which Drupal entity a remote entity should be mapped
 * to.
 */
class EntityMappingEvent extends Event {

  const EVENT_NAME = 'entity_sync.import.entity_mapping';

  /**
   * The entity that's about to be synced.
   *
   * @var object
   */
  protected $remoteEntity;

  /**
   * Define which Drupal entity this remote entity should be mapped to.
   *
   * @var array
   */
  protected $entityMapping;

  /**
   * Constructs the EntityMappingEvent object.
   *
   * @param object $remote_entity
   *   The remote entity that's about to be synced.
   * @param array $entity_mapping
   *   The Drupal entity and ID that this remote entity should be mapped to.
   */
  public function __construct($remote_entity, array $entity_mapping) {
    $this->remoteEntity = $remote_entity;
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
   *
   * @return $this
   */
  public function setEntityMapping(array $entity_mapping) {
    $this->entityMapping = $entity_mapping;
    return $this;
  }

}
