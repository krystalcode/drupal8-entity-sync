<?php

namespace Drupal\entity_sync\Export;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the interface for the export field manager.
 *
 * The export field manager is responsible for preparing the fields of a given
 * local entity that will be exported to a given remote entity. This involves:
 * - Mapping the fields i.e. which local entity fields correspond to which
 *   remote entity fields.
 * - Limiting the fields that will be exported based on whether their values
 *   have changed, if the export is a response to an entity update.
 * - Converting the values of the local entity fields to the format expected by
 *   the remote entity fields.
 */
interface FieldManagerInterface {

  /**
   * Prepares the fields that will be exported for the local entity.
   *
   * The fields are handled as an array because we don't really know the format
   * required by the provider. We therefore prepare them in array format to
   * optimize performance and the client will be formatting them as required for
   * sending them to the remote resource.
   *
   * @param \Drupal\core\Entity\ContentEntityInterface $local_entity
   *   The local entity.
   * @param int|string|null $remote_entity_id
   *   The ID of the remote entity that will be updated, or NULL if we are
   *   creating a new one.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the field
   *   mapping.
   * @param array $options
   *   An associative array of options that determine various aspects of the
   *   export. Currently supported options are:
   *   - context: An associative array of context related to the circumstances
   *     of the operation. It is passed to dispatched events and can help
   *     subscribers determine how to alter list filters and entity/field
   *     mappings.
   *
   * @return array
   *   An associative array containing the remote fields, keyed by the field
   *   name and containing the field value.
   *
   * @throws \Drupal\entity_sync\Exception\FieldExportException
   *   When an error occurs while exporting a field.
   */
  public function export(
    ContentEntityInterface $local_entity,
    $remote_entity_id,
    ImmutableConfig $sync,
    array $options = []
  );

  /**
   * Gets all exportable fields that are different between the given entities.
   *
   * This method calculates and returns the machine names for the fields of the
   * given entity that fulfill the following criteria:
   * - They are set to be exportable by the given field mapping array.
   * - They have different value compared to the original entity.
   * - They are in the values of the `$field_names` array, if provided.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $changed_entity
   *   The entity containing the changed field values.
   * @param \Drupal\Core\Entity\ContentEntityInterface $original_entity
   *   The entity containing the original field values.
   * @param array $field_mapping
   *   The array that defines the field mapping for the export operation.
   * @param string[]|null $names_filter
   *   An array containing field machine names to limit the results to. Passing
   *   an empty array will result in no fields to be returned. To not filter by
   *   any additional names, pass NULL.
   * @param string[]|nul $changed_names
   *   An array containing the machine names of the changed fields, if already
   *   known, or NULL to calculate them.
   *
   * @return string[]
   *   An array containing the machine names of the fields that have been
   *   changed i.e. that are different between the given entities.
   */
  public function getExportableChangedNames(
    ContentEntityInterface $changed_entity,
    ContentEntityInterface $original_entity,
    array $field_mapping,
    array $names_filter = NULL,
    array $changed_names = NULL
  );

  /**
   * Gets the list of fields that are different between the given entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $changed_entity
   *   The entity containing the changed field values.
   * @param \Drupal\Core\Entity\ContentEntityInterface $original_entity
   *   The entity containing the original field values.
   * @param string[]|null $names_filter
   *   An array containing field machine names to limit the results to. Passing
   *   an empty array will result in no fields to be returned. To not filter by
   *   any additional names, pass NULL.
   *
   * @return string[]
   *   An array containing the machine names of the fields that have been
   *   changed i.e. that are different between the given entities.
   */
  public function getChangedNames(
    ContentEntityInterface $changed_entity,
    ContentEntityInterface $original_entity,
    array $names_filter = NULL
  );

  /**
   * Gets all fields that are set to be exportable by the given field mapping.
   *
   * @param array $field_mapping
   *   The array that defines the field mapping for the export operation.
   *
   * @return string[]
   *   An array containing the machine names of exportable fields.
   */
  public function getExportableNames(array $field_mapping);

}
