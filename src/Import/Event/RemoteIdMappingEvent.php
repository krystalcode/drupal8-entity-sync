<?php

namespace Drupal\entity_sync\Import\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the import remote ID mapping event.
 *
 * Allows subscribers to define which remote ID to use to fetch the remote
 * entity.
 */
class RemoteIdMappingEvent extends Event {

  /**
   * The associated local entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $localEntity;

  /**
   * The remote ID.
   *
   * @var int
   */
  protected $remoteId;

  /**
   * The synchronization configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sync;

  /**
   * Constructs a new RemoteIdMapping object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The local entity that is about to be synced.
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
   * Gets the remote ID.
   *
   * @return int
   *   The remote ID.
   */
  public function getRemoteId() {
    return $this->remoteId;
  }

  /**
   * Sets the remote ID.
   *
   * @param int $remote_id
   *   The remote ID.
   */
  public function setRemoteId($remote_id) {
    $this->remoteId = $remote_id;
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
   * Gets the synchronizatio configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The synchronization configuration object.
   */
  public function getSync() {
    return $this->sync;
  }

}
