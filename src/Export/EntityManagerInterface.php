<?php

namespace Drupal\entity_sync\Export;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for the export manager.
 *
 * The export manager is responsible for all export operations, that is,
 * operations that export entities from the local to the remote resource.
 * Currently supported operations are:
 * - Export a local entity to the remote resource.
 */
interface EntityManagerInterface {

  /**
   * Export the local entity.
   */
  const ACTION_EXPORT = 0;

  /**
   * Create a new remote entity as a response to a local entity export.
   */
  const ACTION_CREATE = 1;

  /**
   * Update an existing remote entity as a response to a local entity export.
   */
  const ACTION_UPDATE = 2;

  /**
   * Do nothing as a response to a local entity export.
   */
  const ACTION_SKIP = 3;

  /**
   * Exports the given local entity to the remote resource.
   *
   * The most common use cases are to update the remote entity that is
   * associated to the given local entity, or to create a new remote entity if
   * there is no known association. However, subscribers to the entity mapping
   * event can determine that a different remote entity should be updated, or
   * that a new should be created even if there is a known association.
   *
   * With that in mind:
   *
   * Exporting local entities that already have remote entities associated with
   * them will result in the remote entities being updated.
   *
   * Exporting local entities that do not have remote entities associated with
   * them will result in new remote entities being created, subject to the
   * synchronization configuration.
   *
   * @param string $sync_id
   *   The ID of the entity sync.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   export. Supported options are:
   *   - context: An associative array of context related to the circumstances
   *     of the operation. It is passed to dispatched events and can help
   *     subscribers determine how to alter list filters and entity/field
   *     mappings.
   *
   * @throws \Drupal\entity_sync\Exception\FieldExportException
   *   When an error occurs while exporting a field.
   */
  public function exportLocalEntity(
    $sync_id,
    ContentEntityInterface $local_entity,
    array $options = []
  );

  /**
   * Queues a local entity export operation for the given entity.
   *
   * This method will detect all synchronizations that define the
   * `export_entity` operation for the given entity type and will queue the
   * operation for each one of them.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to queue the export.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   export. Supported options are:
   *   - context: An associative array of context related to the circumstances
   *     of the operation. It is passed to dispatched events and can help
   *     subscribers determine how to alter entity/field mappings. Context items
   *     supported by the Entity Sync module are:
   *     - original_entity: (\Drupal\Core\Entity\ContentEntityInterface) The
   *       original entity before the changes, if we are queueing an export
   *       because of a local entity update. It will be used to detect which
   *       fields have changed so that only those are sent to the remote
   *       resource. Currently, no export will be scheduled if there are no
   *       changed fields.
   *     Third-party modules should be placing their custom context in a
   *     sub-array keyed by the module name to prevent conflicts with context
   *     items used by the Entity Sync module.
   */
  public function queueExportLocalEntityAllSyncs(
    ContentEntityInterface $entity,
    array $options = []
  );

}
