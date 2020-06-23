<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\FieldMappingEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the default field mapping for import operations.
 */
class DefaultImportFieldMapping implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::FIELD_MAPPING => ['buildFieldMapping', 0],
    ];
    return $events;
  }

  /**
   * Builds the default field mapping.
   *
   * The default field mapping is defined in the synchronization configuration
   * object.
   *
   * @param \Drupal\entity_sync\Import\Event\FieldMappingEvent $event
   *   The field mapping event.
   */
  public function buildFieldMapping(FieldMappingEvent $event) {
    $field_mapping = $event->getSync()->get('fields');
    if (!$field_mapping) {
      $field_mapping = [];
    }

    $event->setFieldMapping($field_mapping);
  }

}
