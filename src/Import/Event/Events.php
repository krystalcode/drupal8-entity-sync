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
   * @see \Drupal\entity_sync\Import\Event\LocalEntityMappingEvent
   */
  const LOCAL_ENTITY_MAPPING = 'entity_sync.import.local_entity_mapping';

  /**
   * Name of the event fired when a remote entity is mapped to a local entity.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\RemoteEntityMappingEvent
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

  /**
   * Name of the event fired for altering filters when importing from remote.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Import\Event\ListFiltersEvent
   */
  const REMOTE_LIST_FILTERS = 'entity_sync.import.remote_list_filters';

  /**
   * Name of the event fired after importing remote entities has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const REMOTE_LIST_TERMINATE = 'entity_sync.import.remote_list_terminate';

  /**
   * Name of the event fired after importing a local entity has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const LOCAL_ENTITY_TERMINATE = 'entity_sync.import.local_entity_terminate';

}
