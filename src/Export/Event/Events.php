<?php

namespace Drupal\entity_sync\Export\Event;

/**
 * Defines the events related to exports.
 */
final class Events {

  /**
   * Name of the event fired when a local entity is mapped to a remote entity.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Export\Event\LocalEntityMappingEvent
   */
  const LOCAL_ENTITY_MAPPING = 'entity_sync.export.local_entity_mapping';

  /**
   * Name of the event fired when an export field mapping is being built.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Export\Event\LocalFieldMappingEvent
   */
  const FIELD_MAPPING = 'entity_sync.export.field_mapping';

  /**
   * Name of the event fired after exporting a local entity has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const LOCAL_ENTITY_TERMINATE = 'entity_sync.export.local_entity_terminate';

}
