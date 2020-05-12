<?php

namespace Drupal\entity_sync\Event;

use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the operation terminate event.
 *
 * Allows subscribers to respond after an operation has been executed.
 */
class TerminateOperationEvent extends Event {

  /**
   * The operation that was executed.
   *
   * @var string
   */
  protected $operation;

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
   * Constructs a new OperationTerminated object.
   *
   * @param string $operation
   *   The operation that was executed.
   * @param array $context
   *   The context array.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   */
  public function __construct(
    $operation,
    array $context,
    ImmutableConfig $sync
  ) {
    $this->operation = $operation;
    $this->context = $context;
    $this->sync = $sync;
  }

  /**
   * Gets the operation.
   *
   * @return string
   *   The operation.
   */
  public function getOperation() {
    return $this->operation;
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
