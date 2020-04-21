<?php

namespace Drupal\entity_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * The ClientAdapterEvent event.
 *
 * Allows modules to return the actual client adapter that should be used to
 * make the appropriate calls to the remote service.
 */
class ClientAdapterEvent extends Event {

  const EVENT_NAME = 'entity_sync.client_adapter';

  /**
   * The specific entity sync ID.
   *
   * @var string
   */
  protected $syncId;

  /**
   * The entity type that should be synced.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The entity type bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The client adapter that should be used to make the calls to the remote.
   *
   * @var \Drupal\entity_sync\Client\ClientInterface
   */
  protected $clientAdapter;

  /**
   * Constructs the ClientAdapterEvent object.
   *
   * @param string $sync_id
   *   The specific entity sync ID.
   * @param string $entity_type
   *   The entity type that should be synced.
   * @param string $bundle
   *   The entity type bundle.
   */
  public function __construct($sync_id, $entity_type, $bundle) {
    $this->syncId = $sync_id;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
  }

  /**
   * Gets the client adapter object.
   *
   * @return \Drupal\entity_sync\Client\ClientInterface
   *   An instantiated client interface object.
   */
  public function getClientAdapter() {
    return $this->clientAdapter;
  }

}
