<?php

namespace Drupal\entity_sync\EventSubscriber;

use Drupal\entity_sync\Import\Event\Events;
use Drupal\entity_sync\Import\Event\ListFiltersEvent;
use Drupal\entity_sync\StateManagerInterface;
use Drupal\entity_sync\Utility\DateTime;

use Drupal\Component\Datetime\TimeInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the filters for fetching a remote list of entities.
 *
 * @I Write tests for the managed import filters subscriber
 *    type     : task
 *    priority : high
 *    labels   : import, testing
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
   * run and the end time filter that was used. We use that and we set the time
   * filters to import the entities that have changed from the time the last
   * import left off run to the time of the current run (request) - or to the
   * time defined by a maximum interval set in the context.
   *
   * This system can be used to implement a managed import flow where each
   * import (run via cron or otherwise) would pick up from where the previous
   * run left off and import only a small batch of entities. Otherwise, initial
   * imports in systems with hundreds of thousands or millions of entities can
   * be problematic. The flow can look like the following:
   * - On the first import, start by importing entities changed on datetime A or
   *   later; import maximum 1 day's worth of entities.
   * - On the next import, start by import entities where the first import
   *   stopped i.e. datetime A + 1 day, and import maximum 1 day's worth of
   *   entities.
   *
   * @param \Drupal\entity_sync\Import\Event\ListFiltersEvent $event
   *   The list filters event.
   */
  public function buildFilters(ListFiltersEvent $event) {
    $context = $event->getContext();

    // Only proceed if the context indicates that the import is managed.
    if (!isset($context['state']['manager'])) {
      return;
    }
    if ($context['state']['manager'] !== 'entity_sync') {
      return;
    }

    $filters = $event->getFilters();
    $sync_id = $event->getSync()->get('id');

    // We set the time filters only if we don't have any defined. That allows
    // other subscribers or the caller of the import manager to override the
    // default the times even when the import is managed.
    if (!isset($filters['changed_start'])) {
      $filters['changed_start'] = $this->startTimeFilter(
        $sync_id,
        $context,
        $filters
      );
    }

    if (!isset($filters['changed_end'])) {
      $filters['changed_end'] = $this->endTimeFilter($context, $filters);
    }

    $event->setFilters($filters);

    // Save the current run's state; we'll be using it when the import has
    // terminated to save the last run's state.
    $this->setCurrentRunState($sync_id, $filters);
  }

  /**
   * Calculates and returns the start time filter value.
   *
   * The start time is calculated as follows:
   * - If we have an end time on the operation's last run information, we use
   *   that so that we pick up from where the last run left off.
   * - If we don't have an end time we use the fallback start time, if provided
   *   in the state context. This is where the application explicitly tells us
   *   where to start.
   * - If we have neither an end time from the last run, nor a fallback start
   *   time, we don't set a start time. This should serve the cases where we
   *   import all entities, or all entities up to an end time.
   *
   * @param string $sync_id
   *   The ID of the synchronization configuration the operation belongs to.
   * @param array $context
   *   The context array.
   * @param array $filters
   *   The filters array.
   *
   * @return int
   *   The calculated start time filter value.
   *
   * @throws \InvalidArgumentException
   *   When an interval was defined in the state context but no fallback start
   *   time was given.
   */
  protected function startTimeFilter(
    $sync_id,
    array $context,
    array $filters
  ) {
    // If we are given a maximum interval we do need a start time as a fallback
    // when there is no end time for the last run; that can happen the very
    // first time that the import is run. The Unix epoch could be used i.e. 0 as
    // the default fallback start time, but we'd rather have the user to
    // explicitly define the earliest time that an entity was modified (or where
    // to start anyway).
    //
    // We do the validation in advance (a fallback start time wouldn't be needed
    // if we do have last run information) as otherwise we could have the same
    // configuration working when we have last run info and causing a runtime
    // error when we don't - let's chose safe behavior instead.
    $has_interval = !empty($context['state']['max_interval']);
    if ($has_interval && !isset($context['state']['fallback_start_time'])) {
      throw new \InvalidArgumentException(
        'A fallback start time for the time filters must be given when a maximum interval is defined.'
      );
    }

    // Use the last run's end time, if we have one.
    // @I Eliminate overlap between start and end time
    //    type     : bug
    //    priority : low
    //    labels   : import, state-manager
    //    notes    : Using the end time of the last run as the start time of
    //               the next run may result in some entities being imported
    //               again depending on which operators (> or >=, < or <=) are
    //               used by the remote resource.
    $last_run = $this->stateManager->getLastRun(
      $sync_id,
      'import_list'
    );
    if (!empty($last_run['end_time'])) {
      return $last_run['end_time'];
    }

    // If we do have a fallback start time, use that.
    // Otherwise, we don't have a fallback start time and we don't have an
    // interval (or we would have an exception thrown above). We must be in the
    // case where we import all entities changed at any moment up to the current
    // run time.
    return $context['state']['fallback_start_time'] ?? NULL;
  }

  /**
   * Calculates and returns the end time filter value.
   *
   * Required to be called after the start time filter is calculated.
   *
   * The end time is calculated as follows:
   * - If we don't have an interval given, we use the current request's time. We
   *   therefore throttle imports from one run to another importing as many
   *   entities as available.
   * - If we do have an interval given, we use the time defined by adding the
   *   interval to the start time (with the current request's time being the
   *   upper limit). We therefore throttle imports from one run to another
   *   importing entities in batches limited in number by the interval.
   *
   * @param array $context
   *   The context array.
   * @param array $filters
   *   The filters array.
   *
   * @return int
   *   The calculated end time filter value.
   */
  protected function endTimeFilter(array $context, array $filters) {
    if (empty($context['state']['max_interval'])) {
      return $this->time->getRequestTime();
    }

    return DateTime::timeAfterTime(
      // If we are here we must have a start time; it would be the fallback
      // start time which must exist as otherwise an exception would had been
      // been thrown when calculating the start time.
      $filters['changed_start'],
      $context['state']['max_interval'],
      $this->time->getRequestTime()
    );
  }

  /**
   * Sets the current run state of the operation.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operation belongs to.
   * @param array $filters
   *   The filters containing the final values for the start and end time
   *   filters.
   */
  protected function setCurrentRunState($sync_id, array $filters) {
    $this->stateManager->setCurrentRun(
      $sync_id,
      'import_list',
      $this->time->getRequestTime(),
      $filters['changed_start'] ?? NULL,
      $filters['changed_end'] ?? NULL
    );
  }

}
