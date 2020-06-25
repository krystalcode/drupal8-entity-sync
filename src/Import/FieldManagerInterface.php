<?php

namespace Drupal\entity_sync\Import;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for the import field manager.
 *
 * The import field manager is responsible for importing fields from a given
 * remote entity to a given local entity. That involves:
 * - Mapping the fields i.e. which remote entity fields correspond to which
 *   local entity fields.
 * - Converting the values of the remote entity fields to the format expected by
 *   the local entity fields.
 * - Storing the converted values to the local entity fields. It does not save
 *   the entity.
 */
interface FieldManagerInterface {

  /**
   * Imports the fields from a remote to a local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the field
   *   mapping.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   import. Currently supported options are:
   *   - context: An associative array of context related to the circumstances
   *     of the operation. It is passed to dispatched events and can help
   *     subscribers determine how to alter list filters and entity/field
   *     mappings.
   */
  public function import(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    array $options = []
  );

  /**
   * Sets the remote ID field in the local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param bool $force
   *   When TRUE (default) the remote ID value will be set whether it already
   *   has a value or not. When FALSE, it will be set only if it does not have a
   *   value yet.
   */
  public function setRemoteIdField(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync,
    $force = TRUE
  );

  /**
   * Sets the remote changed field in the local entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $local_entity
   *   The associated local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function setRemoteChangedField(
    object $remote_entity,
    ContentEntityInterface $local_entity,
    ImmutableConfig $sync
  );

}
