<?php

namespace Drupal\entity_sync\SyncFrom;

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
   * @param string $sync_id
   *   The entity sync id.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   */
  public function syncList($sync_id, $entity_type_id, $bundle);

  /**
   * Syncs a remote entity with the appropriate Drupal entity.
   *
   * @param object $remote_entity
   *   The remote entity that should be synced.
   */
  public function sync($remote_entity);

}
