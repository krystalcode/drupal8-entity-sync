<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\entity_sync\Export\EntityManagerInterface;
use Drupal\entity_sync\Import\FieldManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates the local entity after the export local entity terminate event.
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
    $this->fieldManager = $field_manager;
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
   * Update the remote ID and remote changed fields on the local entity.
   *
   * The remote changed field is updated for both new and updated remote
   * entities.
   *
   * The remote ID is only updated when new remote entities are created. We do
   * check however that the field does not already have value - normally it
   * shouldn't, but in rare configurations we may be creating a new remote
   * entity as a result of a local entity update in which case we may have a
   * remote ID already set.
   *
   * @param \Drupal\entity_sync\Event\TerminateOperationEvent $event
   *   The terminate operation event.
   */
  public function updateLocalEntity(TerminateOperationEvent $event) {
    $data = $event->getData();

    $actions = [
      EntityManagerInterface::ACTION_CREATE,
      EntityManagerInterface::ACTION_UPDATE,
    ];
    if (!in_array($data['action'], $actions, TRUE)) {
      return;
    }

    $local_entity = $event->getContext()['local_entity'];
    $this->fieldManager->setRemoteChangedField(
      $data['response'],
      $local_entity,
      $event->getSync()
    );

    if ($data['action'] === EntityManagerInterface::ACTION_CREATE) {
      $this->fieldManager->setRemoteIdField(
        $data['response'],
        $local_entity,
        $event->getSync(),
        // Only if it does not already have a value.
        FALSE
      );
    }

    $local_entity->save();
  }

}
