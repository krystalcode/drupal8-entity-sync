<?php

namespace Drupal\entity_sync\Event;

use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the operation post-terminate event.
 *
 * Allows subscribers to respond after an operation has been terminated.
 */
class PostTerminateOperationEvent extends Event {

  /**
   * The operation that was terminated.
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
   * Custom data related to the operation that was terminated.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs a new PostTerminateOperationEvent object.
   *
   * @param string $operation
   *   The operation that was terminated.
   * @param array $context
   *   The context array.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation that was terminated.
   */
  public function __construct(
    $operation,
    array $context,
    ImmutableConfig $sync,
    array $data = []
  ) {
    $this->operation = $operation;
    $this->context = $context;
    $this->sync = $sync;
    $this->data = $data;
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

  /**
   * Gets the custom data.
   *
   * @return array
   *   The custom data associated with the operation.
   */
  public function getData() {
    return $this->data;
  }

}
