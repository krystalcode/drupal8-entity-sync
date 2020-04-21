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
   * Define which Drupal entity this remote entity should be mapped to.
   *
   * @var array
   */
  protected $entityMapping;

  /**
   * An array with the remote and Drupal fields mapped.
   *
   * @var array
   */
  protected $fieldMapping;

  /**
   * Constructs the EntityUpdateEvent object.
   *
   * @param object $remote_entity
   *   The remote entity that need to be synced.
   * @param array $entity_mapping
   *   Defines which Drupal entity this remote entity should be mapped to.
   * @param array $field_mapping
   *   An array with the remote and Drupal fields mapped.
   */
  public function __construct(
    $remote_entity,
    array $entity_mapping,
    array $field_mapping
  ) {
    $this->remoteEntity = $remote_entity;
    $this->entityMapping = $entity_mapping;
    $this->fieldMapping = $field_mapping;
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
   * Gets the field mapping array.
   *
   * @return array
   *   The field mapping array.
   */
  public function getFieldMapping() {
    return $this->fieldMapping;
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

}
