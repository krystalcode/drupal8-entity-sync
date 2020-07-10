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
   * Name of the event fired before importing remote entities is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PreInitiateOperationEvent
   */
  const REMOTE_LIST_PRE_INITIATE = 'entity_sync.import.remote_list_pre_initiate';

  /**
   * Name of the event fired when importing remote entities is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\InitiateOperationEvent
   */
  const REMOTE_LIST_INITIATE = 'entity_sync.import.remote_list_initiate';

  /**
   * Name of the event fired after importing remote entities has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const REMOTE_LIST_TERMINATE = 'entity_sync.import.remote_list_terminate';

  /**
   * Name of the event fired after importing remote entities has terminated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PostTerminateOperationEvent
   */
  const REMOTE_LIST_POST_TERMINATE = 'entity_sync.import.remote_list_post_terminate';

  /**
   * Name of the event fired before importing a remote entity is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PreInitiateOperationEvent
   */
  const REMOTE_ENTITY_PRE_INITIATE = 'entity_sync.import.remote_entity_pre_initiate';

  /**
   * Name of the event fired when importing a remote entity is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\InitiateOperationEvent
   */
  const REMOTE_ENTITY_INITIATE = 'entity_sync.import.remote_entity_initiate';

  /**
   * Name of the event fired after importing a remote entity has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const REMOTE_ENTITY_TERMINATE = 'entity_sync.import.remote_entity_terminate';

  /**
   * Name of the event fired after importing a remote entity has terminated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PostTerminateOperationEvent
   */
  const REMOTE_ENTITY_POST_TERMINATE = 'entity_sync.import.remote_entity_post_terminate';

  /**
   * Name of the event fired before importing a local entity is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PreInitiateOperationEvent
   */
  const LOCAL_ENTITY_PRE_INITIATE = 'entity_sync.import.local_entity_pre_initiate';

  /**
   * Name of the event fired when importing a local entity is initiated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\InitiateOperationEvent
   */
  const LOCAL_ENTITY_INITIATE = 'entity_sync.import.local_entity_initiate';

  /**
   * Name of the event fired after importing a local entity has finished.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\TerminateOperationEvent
   */
  const LOCAL_ENTITY_TERMINATE = 'entity_sync.import.local_entity_terminate';

  /**
   * Name of the event fired after importing a local entity has terminated.
   *
   * @Event
   *
   * @see \Drupal\entity_sync\Event\PostTerminateOperationEvent
   */
  const LOCAL_ENTITY_POST_TERMINATE = 'entity_sync.import.local_entity_post_terminate';

}
