<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Export\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\Export\ManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the default remote ID mapping for export operations.
 */
class DefaultExportLocalEntityMapping implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::LOCAL_ENTITY_MAPPING => ['buildEntityMapping', 0],
    ];
    return $events;
  }

  /**
   * Builds the default local entity mapping with the remote.
   *
   * The default local entity mapping is defined in the synchronization
   * configuration object.
   *
   * @param \Drupal\entity_sync\Export\Event\LocalEntityMappingEvent $event
   *   The local entity mapping event.
   */
  public function buildEntityMapping(LocalEntityMappingEvent $event) {
    $sync = $event->getSync();
    $local_entity = $event->getLocalEntity();

    // Check if the field exists and has a value.
    $id_field_name = $sync->get('entity.remote_id_field');
    if (!$local_entity->hasField($id_field_name)) {
      return;
    }

    $id_field = $local_entity->get($id_field_name);
    $event->setEntityMapping([
      'action' => ManagerInterface::ACTION_EXPORT,
      'client' => $sync->get('remote_resource.client'),
      'id' => $id_field->value ?: NULL,
    ]);
  }

}
