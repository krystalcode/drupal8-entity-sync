<?php

namespace Drupal\entity_sync;

/**
 * Defines the interface for Entity Sync state managers.
 *
 * The Entity Sync state manager keeps track of the state of a synchronization
 * operation. Currently, it tracks when an operation was last run.
 *
 * @I Add status tracking to operation states that can be used as a lock
 *    type     : feature
 *    priority : normal
 *    labels   : operation, state
 */
interface StateManagerInterface {

  /**
   * Gets the state of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the theoperation.
   *
   * @return array
   *   An associative array containing the properties of the state. Currently
   *   supported properties are:
   *   - last_run: The time that the operation was last run in Unix-timestamp
   *     format.
   */
  public function get($sync_id, $operation);

  /**
   * Gets the time that the operation was last run.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the theoperation.
   *
   * @return int
   *   The time that the operation was last run in Unix-timestamp format.
   */
  public function getLastRun($sync_id, $operation);

  /**
   * Set the time that the operation was last run.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the theoperation.
   * @param int $last_run
   *   The time that the operation was last run in Unix-timestamp format.
   */
  public function setLastRun($sync_id, $operation, $last_run);

}
