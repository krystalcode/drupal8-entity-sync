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
 *
 * @I Review the design of the state manager
 *    type     : task
 *    priority : low
 *    labels   : architecture
 *    notes    : Currently the state manager is only needed to manage import
 *               remote list operations. Review the design so that it allows to
 *               be used by other operations in the future allowing the
 *               operations to store custom data.
 */
interface StateManagerInterface {

  /**
   * Gets the state of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   *
   * @return array
   *   An associative array containing the properties of the state. Currently
   *   supported properties are:
   *   - last_run: The time that the operation was last run in Unix-timestamp
   *     format.
   */
  public function get($sync_id, $operation);

  /**
   * Gets the state of the last run of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   *
   * @return array
   *   An associative array containing information related to the last run of
   *   the operation.
   *   - (int) run_time
   *     The time that the operation was last run in Unix-timestamp format.
   *   - (int) start_time
   *     The start time of the filters' time span that was used to fetch the
   *     list of remote entities.
   *   - (int) end_time
   *     The end time of the filters' time span that was used to fetch the list
   *     of remote entities. It can be used on the next run so that the import
   *     can continue from where it left off.
   */
  public function getLastRun($sync_id, $operation);

  /**
   * Sets the state of the last run of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   * @param int $run_time
   *   The time that the operation was last run in Unix-timestamp format.
   * @param int|null $start_time
   *   The start time of the filters' time span that was used to fetch the list
   *   of remote entities.
   * @param int|null $end_time
   *   The end time of the filters' time span that was used to fetch the list of
   *   remote entities.
   */
  public function setLastRun(
    $sync_id,
    $operation,
    $run_time,
    $start_time = NULL,
    $end_time = NULL
  );

  /**
   * Gets the state of the current run of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   *
   * @return array
   *   An associative array containing information related to the current run of
   *   the operation.
   *   - (int) run_time
   *     The time that the operation started running in Unix-timestamp format.
   *   - (int) start_time
   *     The start time of the filters' time span that was used to fetch the
   *     list of remote entities.
   *   - (int) end_time
   *     The end time of the filters' time span that was used to fetch the list
   *     of remote entities.
   */
  public function getCurrentRun($sync_id, $operation);

  /**
   * Sets the state of the current run of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   * @param int $run_time
   *   The time that the operation was last run in Unix-timestamp format.
   * @param int|null $start_time
   *   The start time of the filters' time span that was used to fetch the list
   *   of remote entities.
   * @param int|null $end_time
   *   The end time of the filters' time span that was used to fetch the list of
   *   remote entities.
   */
  public function setCurrentRun(
    $sync_id,
    $operation,
    $run_time,
    $start_time = NULL,
    $end_time = NULL
  );

  /**
   * Unsets the state of the current run of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param string $operation
   *   The name of the the operation.
   */
  public function unsetCurrentRun($sync_id, $operation);

}
