<?php

namespace Drupal\entity_sync\Import\Event;

use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the import list filters event.
 *
 * Allows subscribers to alter the filters that define which remote entities
 * will be fetched from the remote resource.
 */
class ListFiltersEvent extends Event {

  /**
   * The filters.
   *
   * @var array
   */
  protected $filters;

  /**
   * The context of the operation.
   *
   * @var array
   */
  protected $context;

  /**
   * The synchronization configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $sync;

  /**
   * Constructs a new ListFiltersEvent object.
   *
   * @param array $filters
   *   The filters array.
   * @param array $context
   *   The context array.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function __construct(
    array $filters,
    array $context,
    ImmutableConfig $sync
  ) {
    $this->filters = $filters;
    $this->context = $context;
    $this->sync = $sync;
  }

  /**
   * Gets the filters array.
   *
   * @return array
   *   The filters array.
   */
  public function getFilters() {
    return $this->filters;
  }

  /**
   * Sets the filters array.
   *
   * @param array $filters
   *   The filters array.
   */
  public function setFilters(array $filters) {
    $this->filters = $filters;
  }

  /**
   * Gets the context array.
   *
   * @return array
   *   The context array.
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Gets the synchronization configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The synchronization configuration object.
   */
  public function getSync() {
    return $this->sync;
  }

}
