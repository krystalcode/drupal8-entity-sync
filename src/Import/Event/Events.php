<?php

namespace Drupal\entity_sync\Import\Event;

/**
 * Defines the events related to imports.
 */
final class Events {

  /**
   * Name of the event fired when an import entity mapping is being built.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\EntityMappingEvent
   */
  const ENTITY_MAPPING = 'entity_sync.import.entity_mapping';

  /**
   * Name of the event fired when an import field mapping is being built.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\FieldMappingEvent
   */
  const FIELD_MAPPING = 'entity_sync.import.field_mapping';

  /**
   * Name of the event fired when an import remote ID mapping is being built.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\RemoteIdMappingEvent
   */
  const REMOTE_ID_MAPPING = 'entity_sync.import.remote_id_mapping';

}
