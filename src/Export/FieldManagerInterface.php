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
<<<<<<< HEAD
   * Provides the defaults settings for field mapping.
   *
   * The field mapping settings are loaded by the synchronization
   * configuration. Event subscribers can alter those settings. To make it
   * easier to configure the field mapping settings and not have to define every
   * single setting available for every field, we merge the given settings with
   * the defaults provided by this method.
   *
   * @return array
   *   An associative array with the defaults.
   *
   * @I Provide configuration for defining the default field mapping settings
   *    type     : improvement
   *    priority : low
   *    labels   : config, export, field
   */
  public function mappingDefaults();

  /**
=======
>>>>>>> 8.x-1.x
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

}
