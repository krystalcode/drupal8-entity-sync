<?php

namespace Drupal\entity_sync\Config;

/**
 * Defines the interface for the Entity Sync configuration manager.
 *
 * The configuration manager is responsible for anything relating to Entity Sync
 * configuration, such as, validating configuration, providing defaults etc.
 *
 * @I Define defaults for the whole sync and merge when loading the object
 *    type     : improvement
 *    priority : normal
 *    labels   : config
 */
interface ManagerInterface {

  /**
   * Merges the field mapping defaults into the given field mapping item.
   *
   * @param array $config
   *   An associative array containing the mapping information for a single
   *   field.
   *
   * @return array
   *   An associative array containing the mapping information for the given
   *   field, after merging in the defaults.
   */
  public function mergeFieldMappingDefaults(array $config);

  /**
   * Merges the export field mapping defaults into the given field mapping item.
   *
   * @param array $config
   *   An associative array containing the mapping information for a single
   *   field.
   *
   * @return array
   *   An associative array containing the mapping information for the given
   *   field, after merging in the defaults.
   */
  public function mergeExportFieldMappingDefaults(array $config);

  /**
   * Merges the import field mapping defaults into the given field mapping item.
   *
   * @param array $config
   *   An associative array containing the mapping information for a single
   *   field.
   *
   * @return array
   *   An associative array containing the mapping information for the given
   *   field, after merging in the defaults.
   */
  public function mergeImportFieldMappingDefaults(array $config);

  /**
   * Returns the default field mapping settings.
   *
   * @return array
   *   An associative array containing the field mapping defaults.
   */
  public function fieldMappingDefaults();

  /**
   * Returns the default field mapping settings for export operations.
   *
   * @return array
   *   An associative array containing the field mapping defaults.
   */
  public function exportFieldMappingDefaults();

  /**
   * Returns the default field mapping settings for import operations.
   *
   * @return array
   *   An associative array containing the field mapping defaults.
   */
  public function importFieldMappingDefaults();

}
