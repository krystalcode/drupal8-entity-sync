<?php

namespace Drupal\entity_sync\SyncFrom;

use Drupal\entity_sync\Event\ClientAdapterEvent;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_sync\Event\EntityUpdateEvent;
use Drupal\field\Entity\FieldStorageConfig;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The manager class for syncing entities from a remote service.
 *
 * Helps perform operations related to syncing a list of entities and specific
 * entities, from a remote service to Drupal.
 */
class Manager implements ManagerInterface {

  use StringTranslationTrait;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * An event dispatcher instance.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The client adapter that should be used to make the sync.
   *
   * @var \Drupal\entity_sync\Client\ClientInterface
   */
  protected $clientAdapter;

  /**
   * Constructs a new Manager instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to pass to the client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   *
   * @throws \Exception
   */
  public function __construct(
    LoggerChannelInterface $logger,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritDoc}
   */
  public function syncList($sync_id, $entity_type, $bundle) {
    // First, check if the application is properly set up for syncing this
    // entity.
    // Throw and error if the app isn't ready to sync this entity.
    if (!$this->appReady($entity_type, $bundle)) {
      $message = 'The application is not properly set up to sync this entity.';
      $this->logger->error($message);
      throw new \RuntimeException($message);
    }

    // Now, dispatch a function to return the appropriate client object that
    // will be used to fetch the entities from the remote service.
    $event = new ClientAdapterEvent($sync_id, $entity_type, $bundle);
    $this->eventDispatcher->dispatch(
      ClientAdapterEvent::EVENT_NAME,
      $event
    );
    $this->clientAdapter = $event->getClientAdapter();
    // Throw an error if we don't get back a client adapter object.
    if (!$this->clientAdapter) {
      $message = 'There is no client adapter to sync this entity.';
      $this->logger->error($message);
      throw new \RuntimeException($message);
    }

    // Now, fetch the list of entities to sync.
    $options = [];
    if ($this->clientAdapter->supportsPaging()) {
      $options = [
        'limit' => 100,
      ];
    }
    $entities = $this->clientAdapter->list($options);

    // Finally, sync the entities.
    foreach ($entities as $entity) {
      $this->sync($entity);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function sync($remote_entity) {
    // Dispatch an event to allow modules to update and save this entity.
    try {
      $event = new EntityUpdateEvent($remote_entity);
      $this->eventDispatcher->dispatch(
        EntityUpdateEvent::EVENT_NAME,
        $event
      );
    }
    catch (\Exception $e) {
      $this->logger->error(
        $this->t('An error occurred while syncing from the remote service to Drupal.
         The error was: @error', [
           '@error' => $e->getMessage(),
         ]
      ));
    }
  }

  /**
   * Check if the application is properly set up for syncing the given entity.
   *
   * @param string $entity_type
   *   Then entity type we're trying to sync.
   * @param string $bundle
   *   The bundle of the entity type.
   *
   * @return bool
   *   Returns TRUE if the app is ready for syncing the entity.
   */
  protected function appReady($entity_type, $bundle) {
    $app_ready = TRUE;

    // First check if the entity type bundle has the required fields.
    $required_fields = [
      'field_sync_remote_id',
      'field_sync_changed',
    ];
    foreach ($required_fields as $field_name) {
      if (!$this->bundleHasField(
        $entity_type,
        $bundle,
        $field_name)
      ) {
        $app_ready = FALSE;
      }
    }

    return $app_ready;
  }

  /**
   * Checks if an entity bundle has a specific field.
   *
   * @param string $entity_type
   *   Then entity type.
   * @param string $bundle
   *   The bundle of the entity type.
   * @param string $field_name
   *   The name of the field to check.
   *
   * @return bool
   *   Returns TRUE if the field exists in the entity bundle.
   */
  protected function bundleHasField($entity_type, $bundle, $field_name) {
    $field_storage = FieldStorageConfig::loadByName(
      $entity_type,
      $field_name
    );
    if (
      !empty($field_storage)
      && in_array($bundle, $field_storage->getBundles())
    ) {
      return TRUE;
    }

    return FALSE;
  }

}
