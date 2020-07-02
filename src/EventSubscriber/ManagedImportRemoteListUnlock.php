<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Event\PostTerminateOperationEvent;
use Drupal\entity_sync\StateManagerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Unlock the `import_list` operation just after it has terminated.
 *
 * @I Write tests for the managed import list unlock subscriber
 *    type     : task
 *    priority : high
 *    labels   : import, testing
 */
class ManagedImportRemoteListUnlock implements EventSubscriberInterface {

  /**
   * The Entity Sync state manager.
   *
   * @var \Drupal\entity_sync\StateManagerInterface
   */
  protected $stateManager;

  /**
   * Constructs a new ManagedImportRemoteListUnlock object.
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
      // We give it a very high weight so that it is the first subscriber to
      // run.
      Events::REMOTE_LIST_POST_TERMINATE => ['unlockOperation', 1000],
    ];
    return $events;
  }

  /**
   * Unlocks the operation after it has terminated, if the operation is managed.
   *
   * @param \Drupal\entity_sync\Event\PostTerminateOperationEvent $event
   *   The post-terminate operation event.
   */
  public function unlockOperation(PostTerminateOperationEvent $event) {
    $sync = $event->getSync();
    if ($sync->get('operations.import_list.state.manager') !== 'entity_sync') {
      return;
    }
    if ($sync->get('operations.import_list.state.lock') !== TRUE) {
      return;
    }

    $this->stateManager->unlock($sync->get('id'), 'import_list');
  }

}
