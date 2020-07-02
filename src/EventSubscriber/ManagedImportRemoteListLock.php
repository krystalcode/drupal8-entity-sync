<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Event\PreInitiateOperationEvent;
use Drupal\entity_sync\StateManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Locks the `import_list` operation just before it is initiated.
 *
 * @I Write tests for the managed import list lock subscriber
 *    type     : task
 *    priority : high
 *    labels   : import, testing
 */
class ManagedImportRemoteListLock implements EventSubscriberInterface {

  /**
   * The Entity Sync state manager.
   *
   * @var \Drupal\entity_sync\StateManagerInterface
   */
  protected $stateManager;

  /**
   * Constructs a new ManagedImportRemoteListLock object.
   *
   * @param \Drupal\entity_sync\StateManagerInterface $state_manager
   *   The Entity Sync state manager service.
   */
  public function __construct(StateManagerInterface $state_manager) {
    $this->stateManager = $state_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      // We give it a very low weight so that it is the last subscriber to run.
      Events::REMOTE_LIST_PRE_INITIATE => ['lockOperation', -1000],
    ];
    return $events;
  }

  /**
   * Locks the operation before it is initiated, if the operation is managed.
   *
   * @param \Drupal\entity_sync\Event\PreInitiateOperationEvent $event
   *   The pre-initiate operation event.
   */
  public function lockOperation(PreInitiateOperationEvent $event) {
    $sync = $event->getSync();
    if ($sync->get('operations.import_list.state.manager') !== 'entity_sync') {
      return;
    }
    if ($sync->get('operations.import_list.state.lock') !== TRUE) {
      return;
    }

    // We cancel the operation if it is already locked to prevent concurrent
    // runs.
    if ($this->stateManager->isLocked($sync->get('id'), 'import_list')) {
      $event->cancel('The operation is in a locked state. That may have happened because it is currently running, because a user has manually locked it, or because it got stuck in a locked state due to an error.');
      return;
    }

    $this->stateManager->lock($sync->get('id'), 'import_list');
  }

}
