<?php

namespace Drupal\entity_sync\Import\Event;

/**
 * Defines the events related to imports.
 */
final class Events {

  /**
   * Name of the event fired when a local entity is mapped to a remote entity.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\RemoteIdMappingEvent
   */
  const LOCAL_ENTITY_MAPPING = 'entity_sync.import.local_entity_mapping';

  /**
   * Name of the event fired when a remote entity is mapped to a local entity.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\EntityMappingEvent
   */
  const REMOTE_ENTITY_MAPPING = 'entity_sync.import.remote_entity_mapping';

  /**
   * Name of the event fired when an import field mapping is being built.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\FieldMappingEvent
   */
  const FIELD_MAPPING = 'entity_sync.import.field_mapping';

}
