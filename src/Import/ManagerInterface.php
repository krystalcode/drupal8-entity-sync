<?php

namespace Drupal\entity_sync\Import;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for the import manager.
 *
 * The import manager is responsible for all import operations, that is,
 * operations that import entities from the remote resource into local
 * entities. Currently supported operations are:
 * - Import a list of of entities based on remote filters.
 *
 * @I Support an `importLocalList` operation
 *    type     : feature
 *    priority : low
 *    labels   : import, operation
 *    notes    : The `importLocalList` operation will support importing a list
 *               of entities based on local filters e.g. import the entities
 *               with Drupal IDs 1, 2, 3, 4, 5, 6.
 */
interface ManagerInterface {

  /**
   * Import the remote entity.
   */
  const ACTION_IMPORT = 0;

  /**
   * Create a new local entity as a response to a remote entity import.
   */
  const ACTION_CREATE = 1;

  /**
   * Update an existing local entity as a response to a remote entity import.
   */
  const ACTION_UPDATE = 2;

  /**
   * Do nothing as a response to a remote entity import.
   */
  const ACTION_SKIP = 3;

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
   * them will result in new local entities being created, subject to the
   * synchronization configuration.
   *
   * @param string $sync_id
   *   The ID of the entity sync.
   * @param array $filters
   *   An associative array of filters that determine which entities will be
   *   imported. Supported filters are:
   *   - created_start: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created after or at the given timestamp.
   *   - created_end: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created before or at the given timestamp.
   *   - changed_start: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created or updated after or at the given
   *      timestamp.
   *   - changed_end: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created or updated before or at the given
   *     timestamp.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   import. Currently supported options are:
   *   - context: An associative array of context related to the circumstances
   *     of the operation. It is passed to dispatched events and can help
   *     subscribers determine how to alter list filters and entity/field
   *     mappings.
   *   - limit: The maximum number of entities to import; leave empty or set to
   *     NULL for no limit. The filters define the entities that we will get
   *     from the remote resource. No limit will result in all entities to be
   *     imported, while setting a limit will result in importing to stop when
   *     that limit is reached - which might happen before importing all
   *     incoming entities.
   *
   * @I Support overriding synchronization via options
   *    type     : feature
   *    priority : normal
   *    labels   : config
   * @I Support paging filters
   *    type     : improvement
   *    priority : normal
   *    labels   : import, operation
   * @I Pass the context to all events
   *    type     : improvement
   *    priority : normal
   *    labels   : import, operation
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

  /**
   * Imports the remote entity that is associated with the given local entity.
   *
   * The most common use case is to update the given local entity with the
   * latest changes contained in its associated remote entity. However,
   * subscribers to the entity mapping event can determine that the remote
   * entity should be imported to a different local entity.
   *
   * With that in mind:
   *
   * Importing remote entities that already have local entities associated with
   * them will result in the local entities being updated.
   *
   * Importing remote entities that do not have local entities associated with
   * them will result in new local entities being created, subject to the
   * synchronization configuration..
   *
   * @param string $sync_id
   *   The ID of the entity sync.
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   import. No options are supported yet, it is added to more completely
   *   define the interface. Known options that will be supported are the import
   *   mode and whether to create local entities for incoming remote entities
   *   that do not have local associations yet.
   *
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
  public function importLocalEntity(
    $sync_id,
    EntityInterface $local_entity,
    array $options = []
  );

}
