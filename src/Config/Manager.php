<?php

namespace Drupal\entity_sync\Config;

use Drupal\Component\Utility\NestedArray;

/**
 * Default implementation of the Entity Sync configuration manager.
 */
class Manager implements ManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function mergeExportEntityOperationDefaults(array $config) {
    return NestedArray::mergeDeep(
      $this->exportEntityOperationDefaults(),
      $config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function mergeFieldMappingDefaults(array $config) {
    return NestedArray::mergeDeep(
      $this->fieldMappingDefaults(),
      $config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function mergeExportFieldMappingDefaults(array $config) {
    return NestedArray::mergeDeep(
      $this->exportFieldMappingDefaults(),
      $config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function mergeImportFieldMappingDefaults(array $config) {
    return NestedArray::mergeDeep(
      $this->importFieldMappingDefaults(),
      $config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportEntityOperationDefaults() {
    return [
      'status' => TRUE,
      'create_entities' => [
        'status' => TRUE,
        'queue' => TRUE,
      ],
      'update_entities' => [
        'status' => TRUE,
        'queue' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldMappingDefaults() {
    return $this->exportFieldMappingDefaults() +
      $this->importFieldMappingDefaults();
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldMappingDefaults() {
    return [
      'export' => [
        'status' => TRUE,
        'callback' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldMappingDefaults() {
    return [
      'import' => [
        'status' => TRUE,
        'callback' => FALSE,
      ],
    ];
  }

}
