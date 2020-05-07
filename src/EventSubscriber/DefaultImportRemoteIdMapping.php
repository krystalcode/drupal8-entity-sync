<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\RemoteIdMappingEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the default remote ID mapping for import operations.
 */
class DefaultImportRemoteIdMapping implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::REMOTE_ID_MAPPING => ['buildRemoteIdMapping', 0],
    ];
    return $events;
  }

  /**
   * Builds the default remote ID mapping.
   *
   * The default remote ID mapping is defined in the synchronization
   * configuration object.
   *
   * @param \Drupal\entity_sync\Import\Event\RemoteIdMappingEvent $event
   *   The remote ID mapping event.
   */
  public function buildRemoteIdMapping(RemoteIdMappingEvent $event) {
    $sync = $event->getSync();
    $local_entity = $event->getLocalEntity();
    $remote_id_field = $sync->get('entity.remote_id_field');

    $event->setRemoteId($local_entity->{$remote_id_field}->value);
  }

}
