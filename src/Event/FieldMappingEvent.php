<?php

namespace Drupal\entity_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event that gets dispatched when an entity is about to be synced.
 *
 * Allows modules to define which Drupal fields will be synced to which remote
 * fields.
 */
class FieldMappingEvent extends Event {

  const EVENT_NAME = 'entity_sync.import.field_mapping';

  /**
   * The remote entity that's about to be synced.
   *
   * @var object
   */
  protected $remoteEntity;

  /**
   * The associated Drupal entity, if one exists.
   *
   * @var object
   */
  protected $drupalEntity;

  /**
   * An array with the remote and Drupal entity fields mapped.
   *
   * @var array
   */
  protected $fieldMapping;

  /**
   * Constructs the FieldMappingEvent object.
   *
   * @param object $remote_entity
   *   The remote entity that's about to be synced.
   * @param object $drupal_entity
   *   The associated Drupal entity.
   * @param array $field_mapping
   *   An array of remote field names with the Drupal mapping information.
   */
  public function __construct(
    $remote_entity,
    $drupal_entity,
    array $field_mapping
  ) {
    $this->remoteEntity = $remote_entity;
    $this->drupalEntity = $drupal_entity;
    $this->fieldMapping = $field_mapping;
  }

  /**
   * Gets the remote entity.
   *
   * @return object
   *   The remote entity.
   */
  public function getRemoteEntity() {
    return $this->remoteEntity;
  }

  /**
   * Gets the Drupal entity.
   *
   * @return object
   *   The Drupal entity.
   */
  public function getDrupalEntity() {
    return $this->drupalEntity;
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
   *
   * @return $this
   */
  public function setFieldMapping(array $field_mapping) {
    $this->fieldMapping = $field_mapping;
    return $this;
  }

}
