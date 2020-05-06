<?php

namespace Drupal\entity_sync\Import;

/**
 * Defines the interface for the import manager.
 *
 * The import manager is responsible for all import operations, that is,
 * operations that import entities from the remote resource into local
 * entities. Currently supported operations are:
 * - Import a list of of entities based on remote filters.
 *
 * @I Support a `importLocalList` operation
 *    type     : feature
 *    priority : low
 *    labels   : import, operation
 *    notes    : The `importLocalList` operation will support importing a list
 *               of entities based on local filters e.g. import the entities
 *               with Drupal IDs 1, 2, 3, 4, 5, 6.
 */
interface ManagerInterface {

  /**
   * Create a new local entity as a response to a remote entity import.
   */
  const ACTION_CREATE = 0;

  /**
   * Update an existing local entity as a response to a remote entity import.
   */
  const ACTION_UPDATE = 1;

  /**
   * Do nothing as a response to a remote entity import.
   */
  const ACTION_SKIP = 2;

  /**
   * Imports a list of entities from the remote resource.
   *
   * The list of entities to import is determined by remote filters e.g. import
   * all the entities that have changed in the remote resource between the given
   * times.
   *
   * Importing remote entities that already have local entities associated with
   * them will result in the local entities being updated.
   *
   * Importing remote entities that do not have local entities associated with
   * them will result new local entities being created.
   *
   * @param string $sync_id
   *   The ID of the entity sync.
   * @param array $filters
   *   An associative array of filters that determine which entities will be
   *   imported. Supported filters are:
   *   - fromTime: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated after or at the given
   *     timestamp.
   *   - toTime: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated before or at the given
   *     timestamp.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   import. No options are supported yet, it is added to more completely
   *   define the interface. Known options that will be supported are the import
   *   mode and whether to create local entities for incoming remote entities
   *   that do not have local associations yet.
   *
   * @I Support disabling local entity creation for unassociated remote entities
   *    type     : improvement
   *    priority : normal
   *    labels   : import, operation
   * @I Support importing list of entities based on their remote IDs
   *    type     : improvement
   *    priority : normal
   *    labels   : import, operation
   * @I Implement import modes
   *    type     : feature
   *    priority : normal
   *    labels   : import, operation
   *    notes    : Import modes define different courses of action when
   *               importing entity fields such as to always override the local
   *               value with the remote, to only import the value if the field
   *               is empty, or to import value only when the entity is being
   *               created.
   */
  public function importRemoteList(
    $sync_id,
    array $filters = [],
    array $options = []
  );

}
