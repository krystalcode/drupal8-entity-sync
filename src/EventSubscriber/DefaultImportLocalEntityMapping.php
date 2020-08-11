<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\LocalEntityMappingEvent;
use Drupal\entity_sync\Import\ManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the default remote ID mapping for import operations.
 */
class DefaultImportLocalEntityMapping implements EventSubscriberInterface {

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
   * Builds the default local entity mapping.
   *
   * The default local entity mapping is defined in the synchronization
   * configuration object.
   *
   * @param \Drupal\entity_sync\Import\Event\LocalEntityMappingEvent $event
   *   The remote ID mapping event.
   */
  public function buildEntityMapping(LocalEntityMappingEvent $event) {
    $sync = $event->getSync();
    $local_entity = $event->getLocalEntity();

    // Check if the field exists and has a value.
    $id_field_name = $sync->get('local_entity.remote_id_field');
    if (!$local_entity->hasField($id_field_name)) {
      return [];
    }

    $id_field = $local_entity->get($id_field_name);
    if ($id_field->isEmpty()) {
      return [];
    }

    $event->setEntityMapping([
      'action' => ManagerInterface::ACTION_IMPORT,
      'client' => $sync->get('remote_resource.client'),
      'entity_id' => $id_field->value,
    ]);
  }

}
