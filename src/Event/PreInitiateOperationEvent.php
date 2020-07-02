<?php

namespace Drupal\entity_sync\Event;

use Drupal\Core\Config\ImmutableConfig;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the operation pre-initiate event.
 *
 * Allows subscribers to act before an operation is initiated. Subscribers are
 * also given the opportunity to cancel the operation.
 */
class PreInitiateOperationEvent extends Event {

  /**
   * The operation that is being executed.
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
   * Custom data related to the operation that is being executed.
   *
   * @var array
   */
  protected $data;

  /**
   * Whether the operation should be cancelled.
   *
   * @var bool
   */
  protected $cancel;

  /**
   * Text messages containing the reason(s) for cancellation.
   *
   * @var array
   */
  protected $cancellationMessages;

  /**
   * Constructs a new PreInitiateOperationEvent object.
   *
   * @param string $operation
   *   The operation that is being executed.
   * @param array $context
   *   The context array.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation that is being executed.
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

    $this->cancel = FALSE;
    $this->cancellationMessages = [];
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

  /**
   * Sets the operation to be cancelled with the given message.
   *
   * @param string $message
   *   The reason for the cancellation; it will be logged as a warning by the
   *   logger service.
   */
  public function cancel($message) {
    $this->cancel = TRUE;
    $this->cancellationMessages[] = $message;
  }

  /**
   * Gets whether the operation should be cancelled with any messages available.
   *
   * @return array
   *   An array containing the following elements in the given order:
   *   - A boolean that is TRUE if the operation should be cancelled, FALSE
   *     otherwise.
   *   - An array of text messages with the reason(s) that the operation was
   *     cancelled, if applicable.
   */
  public function getCancellations() {
    return [$this->cancel, $this->cancellationMessages];
  }

}
