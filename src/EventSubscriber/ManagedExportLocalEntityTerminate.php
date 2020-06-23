<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Export\Event\Events;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\entity_sync\Export\EntityManagerInterface;
use Drupal\entity_sync\Export\FieldManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates the local entity after the export terminate event.
 */
class ManagedExportLocalEntityTerminate implements EventSubscriberInterface {

  /**
   * The Entity Sync export field manager.
   *
   * @var \Drupal\entity_sync\Export\FieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Constructs a new ManagedExportLocalEntityTerminate object.
   *
   * @param \Drupal\entity_sync\Export\FieldManagerInterface $field_manager
   *   The export field manager manager.
   */
  public function __construct(FieldManagerInterface $field_manager) {
    $this->fieldManager = $$field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      Events::LOCAL_ENTITY_TERMINATE => ['setRemoteId', 0],
    ];
    return $events;
  }

  /**
   * Update the local entity with the newly created remote entity's ID.
   *
   * @param \Drupal\entity_sync\Event\TerminateOperationsEvent $event
   *   The terminate operation event.
   */
  public function setRemoteId(TerminateOperationEvent $event) {
    $context = $event->getContext();
    $data = $event->getData();

    // Don't do anything if this was for updating an existing entity.
    if ($data['action'] === EntityManagerInterface::ACTION_UPDATE) {
      return;
    }

    $this->fieldManager->saveRemoteIdField(
      $data['response'],
      $context['local_entity'],
      $event->getSync()
    );
  }

}
