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

}
