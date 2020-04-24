<?php

namespace Drupal\entity_sync\Import;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for the Manager class.
 */
interface ManagerInterface {

  /**
   * Syncs a list of entities that need to be updated from the remote.
   *
   * Fetches a list of entities for the given entity type from the remote
   * service and syncs them with Drupal.
   *
   * @param string $sync_type_id
   *   The ID of the entity sync type.
   */
  public function syncList($sync_type_id);

  /**
   * Syncs a remote entity with the appropriate Drupal entity.
   *
   * @param object $remote_entity
   *   The remote entity that should be synced.
   */
  public function sync($remote_entity);

  /**
   * Performs the actual import and the syncing of the fields from the remote.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\EntityInterface $drupal_entity
   *   The associated Drupal entity.
   * @param array $field_info
   *   The field info.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The Drupal entity.
   */
  public function importField(
    $remote_entity,
    EntityInterface $drupal_entity,
    array $field_info
  );

}
