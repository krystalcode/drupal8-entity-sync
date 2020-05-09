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

  /**
   * Provides a list of all entity sync operation for which a route is needed.
   *
   * @return array
   *   Associated array with operation id as key and form class as value.
   */
  public function getSyncOperationsForRoute();

}
