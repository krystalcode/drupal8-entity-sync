<?php

namespace Drupal\entity_sync;

/**
 * Defines the interface for the sync manager.
 */
interface ManagerInterface {

  /**
   * Provides a list of all entity sync configurations in the system.
   *
   * @return array
   *   Array of sync configurations.
   */
  public function getAllSyncConfig();

}
