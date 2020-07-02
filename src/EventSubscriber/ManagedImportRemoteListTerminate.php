<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\entity_sync\StateManagerInterface;

use Drupal\Component\Datetime\TimeInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to a managed import of remote entities having finished.
 *
 * It sets the last run time for the operation so that the next run can continue
 * from there.
 */
class ManagedImportRemoteListTerminate implements EventSubscriberInterface {

  /**
   * The Entity Sync state manager.
   *
   * @var \Drupal\entity_sync\StateManagerInterface
   */
  protected $stateManager;

  /**
   * The system time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new ManagedImportRemoteListTerminate object.
   *
   * @param \Drupal\entity_sync\StateManagerInterface $state_manager
   *   The Entity Sync state manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time service.
   */
  public function __construct(
    StateManagerInterface $state_manager,
    TimeInterface $time
  ) {
    $this->stateManager = $state_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      // We give it a very low weight so that it is the last subscriber to run.
      Events::REMOTE_LIST_TERMINATE => ['setLastRun', -1000],
    ];
    return $events;
  }

  /**
   * Sets the last run's state when a remote list import is terminating.
   *
   * @param \Drupal\entity_sync\Import\Event\TerminateOperationEvent $event
   *   The terminate operation event.
   */
  public function setLastRun(TerminateOperationEvent $event) {
    $context = $event->getContext();

    // Only proceed if the context indicates that the import is managed.
    if (!isset($context['state']['manager'])) {
      return;
    }
    if ($context['state']['manager'] !== 'entity_sync') {
      return;
    }

    $sync_id = $event->getSync()->get('id');
    $current_run = $this->stateManager->getCurrentRun($sync_id, 'import_list');

    $this->stateManager->setLastRun(
      $event->getSync()->get('id'),
      $event->getOperation(),
      $this->time->getRequestTime(),
      $current_run['start_time'] ?? NULL,
      $current_run['end_time'] ?? NULL
    );
    $this->stateManager->unsetCurrentRun($sync_id, 'import_list');
  }

}
