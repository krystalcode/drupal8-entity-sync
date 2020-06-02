<?php

namespace Drupal\entity_sync\Export;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for the export manager.
 *
 * The export manager is responsible for all export operations, that is,
 * operations that export entities from the local to the remote resource.
 * Currently supported operations are:
 * - Export a local entity to the remote resource.
 */
interface ManagerInterface {

  /**
   * Export the local entity.
   */
  const ACTION_EXPORT = 0;

  /**
   * Do nothing as a response to a local entity update.
   */
  const ACTION_SKIP = 3;

  /**
   * Exports a local entity to the remote resource.
   *
   * @param string $sync_id
   *   The ID of the entity sync.
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The local entity.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   export. No options are supported yet, it is added to more completely
   *   define the interface.
   */
  public function exportLocalEntity(
    $sync_id,
    EntityInterface $local_entity,
    array $options = []
  );

}
