<?php

namespace Drupal\entity_sync\Import;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Event\EntityMappingEvent;
use Drupal\entity_sync\Event\FieldMappingEvent;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The client factory.
   *
   * @var \Drupal\entity_sync\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The remote ID field name for this sync entity type.
   *
   * @var string
   */
  protected $remoteIdFieldName;

  /**
   * Constructs a new Manager instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger to pass to the client.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time interface.
   * @param \Drupal\entity_sync\Client\ClientFactory $client_factory
   *   The client factory.
   *
   * @throws \Exception
   */
  public function __construct(
    LoggerChannelInterface $logger,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    ClientFactory $client_factory
  ) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritDoc}
   */
  public function syncList($sync_type_id) {
    // Get the config for this sync type.
    $this->config = $this->configFactory
      ->get('entity_sync.entity_sync_type.' . $sync_type_id);

    // Get the remote ID field for this sync type.
    $this->remoteIdFieldName =
      !empty($this->config->get('entity.remote_id_field'))
        ? $this->config->get('entity.remote_id_field')
        : 'sync_remote_id';

    // First, check if the application is properly set up for syncing this
    // entity.
    // Throw and error if the app isn't ready to sync this entity.
    if (!$this->appReady()) {
      $message = 'The application is not properly set up to sync this entity.';
      $this->logger->error($message);
      throw new \RuntimeException($message);
    }

    // Now, use the remote service to fetch the list of entities.
    $entities = $this->clientFactory->get($sync_type_id)->list();
    if (!$entities) {
      return;
    }

    // Finally, sync the entities.
    foreach ($entities as $remote_entity) {
      $this->sync($remote_entity);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function sync($remote_entity) {
    // Fetch the entity mapping for this remote entity.
    $entity_mapping = $this->getEntityMapping($remote_entity);
    if (!$entity_mapping) {
      $message = 'There is no entity mapping information for this entity.';
      $this->logger->error($message);
      return;
    }

    // Fetch the Drupal entity that this remote entity is associated with, if
    // one exists.
    $remote_identifier = $this->config->get('remote_resource.identifier');
    $remote_id = $remote_entity->{$remote_identifier};
    $drupal_entity = $this->getDrupalEntity($remote_id, $entity_mapping);

    // Fetch the field mapping for this remote entity.
    $fields = $this->getFieldMapping($remote_entity, $drupal_entity);
    if (!$fields) {
      $message = 'There is no field mapping information for this entity.';
      $this->logger->error($message);
      return;
    }

    // Finally, do the field syncing.
    foreach ($fields as $field_info) {
      $this->importField($remote_entity, $drupal_entity, $field_info);
    }

    // Save the Drupal entity now.
    $drupal_entity->set($this->remoteIdFieldName, $remote_id);
    $drupal_entity->set('sync_changed', $this->time->getRequestTime());
    $drupal_entity->save();
  }

  /**
   * {@inheritDoc}
   */
  public function importField(
    $remote_entity,
    EntityInterface $drupal_entity,
    array $field_info
  ) {
    // If this field should be saved via custom callback, then invoke that.
    if (isset($field_info['callback'])) {
      call_user_func(
        $field_info['callback'],
        $remote_entity,
        $drupal_entity,
        $field_info
      );
    }
    // Else, we use the normal set() function.
    elseif ($drupal_entity->hasField($field_info['name'])) {
      $drupal_entity->set(
        $field_info['name'],
        $remote_entity->{$field_info['remote_name']}
      );
    }
  }

  /**
   * Check if the application is properly set up for syncing the given entity.
   *
   * @return bool
   *   Returns TRUE if the app is ready for syncing the entity.
   */
  protected function appReady() {
    $app_ready = TRUE;

    // First check if the entity type bundle has the required fields.
    $required_fields = [
      $this->remoteIdFieldName,
      'sync_changed',
    ];
    foreach ($required_fields as $field_name) {
      if (!$this->bundleHasField($field_name)) {
        $app_ready = FALSE;
      }
    }

    return $app_ready;
  }

  /**
   * Checks if an entity bundle has a specific field.
   *
   * @param string $field_name
   *   The name of the field to check.
   *
   * @return bool
   *   Returns TRUE if the field exists in the entity bundle.
   */
  protected function bundleHasField($field_name) {
    $entity_info = $this->config->get('entity');
    $field_storage = FieldStorageConfig::loadByName(
      $entity_info['entity_type_id'],
      $field_name
    );
    if (
      !empty($field_storage)
      && in_array($entity_info['entity_bundle'], $field_storage->getBundles())
    ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the entity mapping for this remote entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   *
   * @return array
   *   The final entity mapping.
   */
  protected function getEntityMapping($remote_entity) {
    // Dispatch an event to allow modules to alter which Drupal entity and ID to
    // sync this remote entity with.
    $event = new EntityMappingEvent(
      $remote_entity,
      $this->config->get('entity')
    );
    $this->eventDispatcher->dispatch(
      EntityMappingEvent::EVENT_NAME,
      $event
    );

    // Fetch the final mappings.
    return $event->getEntityMapping();
  }

  /**
   * Get the field mapping for this remote entity.
   *
   * @param object $remote_entity
   *   The remote entity.
   * @param \Drupal\core\Entity\EntityInterface $drupal_entity
   *   The Drupal entity.
   *
   * @return array
   *   The final field mapping.
   */
  protected function getFieldMapping(
    $remote_entity,
    EntityInterface $drupal_entity
  ) {
    // Now, dispatch another event to allow modules to alter which Drupal entity
    // fields will be synced to which remote entity fields.
    $event = new FieldMappingEvent(
      $remote_entity,
      $drupal_entity,
      $this->config->get('fields')
    );
    $this->eventDispatcher->dispatch(
      FieldMappingEvent::EVENT_NAME,
      $event
    );

    // Fetch the final mappings.
    return $event->getFieldMapping();
  }

  /**
   * Fetch/create a Drupal entity for this remote entity.
   *
   * @param string $remote_id
   *   The remote ID of the entity.
   * @param array $entity_mapping
   *   The entity mapping info.
   *
   * @return \Drupal\core\Entity\EntityInterface
   *   A Drupal entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getDrupalEntity($remote_id, array $entity_mapping) {
    $entity_storage = $this
      ->entityTypeManager
      ->getStorage($entity_mapping['entity_type_id']);

    // Check if a Drupal entity exists for this entity.
    $results = $entity_storage
      ->getQuery()
      ->condition($this->remoteIdFieldName, $remote_id)
      ->execute();
    if ($results) {
      return $entity_storage->load(reset($results));
    }

    // If this is entity doesn't exist in Drupal, create it.
    $drupal_entity = $entity_storage
      ->create([
        'type' => $entity_mapping['entity_bundle'],
        'status' => TRUE,
      ]);
    $drupal_entity->save();

    return $drupal_entity;
  }

}
