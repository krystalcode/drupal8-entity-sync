<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\entity_sync\Export\EntityManagerInterface;
use Drupal\entity_sync\Import\FieldManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates the local entity after the export terminate event.
 */
class ManagedExportLocalEntityTerminate implements EventSubscriberInterface {

  /**
   * The Entity Sync import field manager.
   *
   * @var \Drupal\entity_sync\Import\FieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Constructs a new ManagedExportLocalEntityTerminate object.
   *
   * @param \Drupal\entity_sync\Import\FieldManagerInterface $field_manager
   *   The import field manager.
   */
  public function __construct(FieldManagerInterface $field_manager) {
    $this->fieldManager = $$field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::LOCAL_ENTITY_TERMINATE => ['updateLocalEntity', 0],
    ];
    return $events;
  }

  /**
   * Update the local entity with the newly created remote entity's ID.
   *
   * Also update the changed timestamp.
   *
   * @param \Drupal\entity_sync\Event\TerminateOperationsEvent $event
   *   The terminate operation event.
   */
  public function updateLocalEntity(TerminateOperationEvent $event) {
    $context = $event->getContext();
    $data = $event->getData();

    // Don't do anything if this was for updating an existing entity.
    if ($data['action'] === EntityManagerInterface::ACTION_UPDATE) {
      return;
    }

    // Save the remote ID and remote changed values to the local entity.
    $local_entity = $context['local_entity'];
    $this->fieldManager->setRemoteIdField(
      $data['response'],
      $local_entity,
      $event->getSync()
    );
    $this->fieldManager->setRemoteChangedField(
      $data['response'],
      $local_entity,
      $event->getSync()
    );
    $local_entity->save();
  }

}
