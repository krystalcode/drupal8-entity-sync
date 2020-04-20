<?php

namespace Drupal\entity_sync\SyncFrom;

/**
 * Interface for the Manager class.
 */
interface ManagerInterface {

  /**
   * Gets and syncs a list of entities that need to be updated from the remote.
   *
   * @param string $sync_id
   *   The entity sync id.
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   */
  public function sync($sync_id, $entity_type_id, $bundle);

}
