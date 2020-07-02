<?php

namespace Drupal\entity_sync;

use Drupal\entity_sync\Event\InitiateOperationEvent;
use Drupal\entity_sync\Event\PostTerminateOperationEvent;
use Drupal\entity_sync\Event\PreInitiateOperationEvent;
use Drupal\entity_sync\Event\TerminateOperationEvent;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Base class for the import/export entity managers.
 */
class EntityManagerBase {

  /**
   * Checks that the given operation is enabled for the given synchronization.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for the synchronization that defines the
   *   operation we are currently executing.
   * @param string $operation
   *   The operation to check.
   *
   * @return bool
   *   TRUE if the operation is enabled and supported, FALSE otherwise.
   */
  protected function operationSupported(ImmutableConfig $sync, $operation) {
    // @I Check that the provider supports the corresponding method as well
    //    type     : bug
    //    priority : normal
    //    labels   : operation, validation
    if (!$sync->get("operations.$operation.status")) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Dispatches an event before an operation is initiated.
   *
   * The `PreInitiateOperationEvent` event allows subscribers to set the
   * operation to be cancelled. This method collects any cancellations, logs
   * their messages, and returns a boolean indicating whether the operation
   * should be cancelled or not so that the caller can act accordingly.
   *
   * @param string $event_name
   *   The name of the event to dispatch. It must be a name for a
   *   `PreInitiateOperationEvent` event.
   * @param string $operation
   *   The name of the operation that will be initiated.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation.
   *
   * @return bool
   *   Whether the operation should be cancelled or not.
   */
  protected function preInitiate(
    $event_name,
    $operation,
    array $context,
    ImmutableConfig $sync,
    array $data = []
  ) {
    $event = new PreInitiateOperationEvent(
      $operation,
      $context,
      $sync,
      $data
    );
    $this->eventDispatcher->dispatch($event_name, $event);

    $cancel = $messages = NULL;
    [$cancel, $messages] = $event->getCancellations();
    if (!$cancel) {
      return FALSE;
    }

    foreach ($messages as $message) {
      $this->logger->warning(
        sprintf(
          'The %s operation for the synchronization with ID "%s" was cancelled with message: %s',
          $operation,
          $sync->get('id'),
          $message
        )
      );
    }

    return TRUE;
  }

  /**
   * Dispatches an event when an operation is being initiated.
   *
   * @param string $event_name
   *   The name of the event to dispatch. It must be a name for a
   *   `InitiateOperationEvent` event.
   * @param string $operation
   *   The name of the operation being initiated.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation.
   */
  protected function initiate(
    $event_name,
    $operation,
    array $context,
    ImmutableConfig $sync,
    array $data = []
  ) {
    $event = new InitiateOperationEvent(
      $operation,
      $context,
      $sync,
      $data
    );
    $this->eventDispatcher->dispatch($event_name, $event);
  }

  /**
   * Dispatches an event when an operation is being terminated.
   *
   * @param string $event_name
   *   The name of the event to dispatch. It must be a name for a
   *   `TerminateOperationEvent` event.
   * @param string $operation
   *   The name of the operation being terminated.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation.
   */
  protected function terminate(
    $event_name,
    $operation,
    array $context,
    ImmutableConfig $sync,
    array $data = []
  ) {
    $event = new TerminateOperationEvent(
      $operation,
      $context,
      $sync,
      $data
    );
    $this->eventDispatcher->dispatch($event_name, $event);
  }

  /**
   * Dispatches an event after an operation terminated.
   *
   * @param string $event_name
   *   The name of the event to dispatch. It must be a name for a
   *   `PostTerminateOperationEvent` event.
   * @param string $operation
   *   The name of the operation that terminated.
   * @param array $context
   *   The context of the operation we are currently executing.
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for synchronization that defines the operation
   *   we are currently executing.
   * @param array $data
   *   Custom data related to the operation.
   *
   * @I Inform post-terminate subscribers whether the operation succeeded
   *    type     : improvement
   *    priority : normal
   *    labels   : event, import, operation
   */
  protected function postTerminate(
    $event_name,
    $operation,
    array $context,
    ImmutableConfig $sync,
    array $data = []
  ) {
    $event = new PostTerminateOperationEvent(
      $operation,
      $context,
      $sync,
      $data
    );
    $this->eventDispatcher->dispatch($event_name, $event);
  }

}
