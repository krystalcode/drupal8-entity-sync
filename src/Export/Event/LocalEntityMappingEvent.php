<?php

namespace Drupal\entity_sync\Export\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the export local entity mapping event.
 *
 * Allows subscribers to define which remote entity a local entity should be
 * mapped to.
 */
class LocalEntityMappingEvent extends Event {

  /**
   * The entity mapping for the local entity being mapped.
   *
   * The mapping is an associative array that defines which remote entity the
   * local entity should be mapped to. Supported array elements:
   * - action: The action to be taken. Possible values are:
   *   - ManagerInterface::ACTION_SKIP: Do not export the entity.
   *   - ManagerInterface::ACTION_EXPORT: Export the remote entity.
   *   See \Drupal\entity_sync\Export\ManagerInterface.
   * - client: An associative array that contains the details of the client that
   *   will be used to export the local entity. Supported elements are:
   *   - type: The type of the client; currently supported type is `service`.
   *   - service: The Drupal service that provides the client.
   * - id: The remote ID of the entity that will be exported, if the entity
   *   already exists on the remote.
   *
   * @var array
   */
  protected $entityMapping = [];

  /**
   * The local entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $localEntity;

  /**
   * The synchronization configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sync;

  /**
   * Constructs a new LocalEntityMappingEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The local entity that's being mapped.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function __construct(
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $this->localEntity = $local_entity;
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
   * Gets the local entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The local entity.
   */
  public function getLocalEntity() {
    return $this->localEntity;
  }

  /**
   * Gets the synchronization configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The synchronization configuration object.
   */
  public function getSync() {
    return $this->sync;
  }

}
