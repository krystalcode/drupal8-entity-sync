<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\ListFiltersEvent;
use Drupal\entity_sync\StateManagerInterface;

use Drupal\Component\Datetime\TimeInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the filters for fetching a remote list of entities.
 */
class ManagedImportRemoteListFilters implements EventSubscriberInterface {

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
   * Constructs a new ManagedImportRemoteListFilters object.
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
      Events::REMOTE_LIST_FILTERS => ['buildFilters', 0],
    ];
    return $events;
  }

  /**
   * Builds the filters for importing a list of remote entities.
   *
   * The default state manager keeps track of the time the import was last
   * run. We use that and we set the time filters to import the entities that
   * have changed from the time the import was last run till the time of the
   * request.
   *
   * @param \Drupal\entity_sync\Import\Event\ListFiltersEvent $event
   *   The list filters event.
   */
  public function buildFilters(ListFiltersEvent $event) {
    $context = $event->getContext();

    // Only proceed if the context indicates that the import is managed.
    if (!isset($context['state_manager'])) {
      return;
    }
    if ($context['state_manager'] !== 'entity_sync') {
      return;
    }

    $filters = $event->getFilters();

    // We set the time filters only if we don't have any defined. That allows
    // other subscribers or the caller of the import manager to override the
    // default the times even when the import is managed.
    if (!isset($filters['changed_start'])) {
      $last_run = $this->stateManager->getLastRun(
        $event->getSync()->get('id'),
        'import_list'
      );
      if ($last_run) {
        $filters['changed_start'] = $last_run;
      }
    }

    if (!isset($filters['changed_end'])) {
      $filters['changed_end'] = $this->time->getRequestTime();
    }

    $event->setFilters($filters);
  }

}
