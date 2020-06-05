<?php

namespace Drupal\entity_sync\Export\Event;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;

use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the import field mapping event.
 *
 * Allows subscribers to define which local entity fields the remote entity
 * fields should be mapped to.
 */
class LocalFieldMappingEvent extends Event {

  /**
   * The field mapping for the entities being mapped.
   *
   * The mapping is an associative array that defines which local entity fields
   * the remote entity fields should be mapped to. Supported array elements are
   * those defined in the `entity_sync.field` configuration.
   * See `config/schema/entity_sync.schema.yml`.
   *
   * @var array
   */
  protected $fieldMapping = [];

  /**
   * The associated local entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $localEntity;

  /**
   * The synchronization configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sync;

  /**
   * Constructs a new FieldMappingEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $local_entity
   *   The associated local entity.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function __construct(
    EntityInterface $local_entity,
    ImmutableConfig $sync
  ) {
    $this->localEntity = $local_entity;
    $this->sync = $sync;
  }

  /**
   * Gets the field mapping array.
   *
   * @return array
   *   The field mapping array.
   */
  public function getFieldMapping() {
    return $this->fieldMapping;
  }

  /**
   * Sets the field mapping.
   *
   * @param array $field_mapping
   *   The field mapping array.
   */
  public function setFieldMapping(array $field_mapping) {
    $this->fieldMapping = $field_mapping;
  }

  /**
   * Gets the local entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The local entity.
   */
  public function getLocalEntity() {
    return $this->localEntity;
  }

  /**
   * Gets the synchronizatio configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The synchronization configuration object.
   */
  public function getSync() {
    return $this->sync;
  }

}
