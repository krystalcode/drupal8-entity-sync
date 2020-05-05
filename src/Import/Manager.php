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
use Drupal\Core\State\StateInterface;
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
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
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
    StateInterface $state,
    ClientFactory $client_factory
  ) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->state = $state;
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritDoc}
   */
  public function syncList($sync_type_id, array $options) {
    // Initialize the sync type.
    $this->initializeSyncType($sync_type_id);

    // Validate the sync type.
    $this->validateSyncType();

    // Form the options for the request.
    // Fetch the last synced time.
    $last_synced_state_name = 'entity_sync.' . $sync_type_id . '.import.' . 'last_synced';
    $options = $this->getRequestOptions($options, $last_synced_state_name);

    // Now, use the remote service to fetch the list of entities.
    $entities = $this->clientFactory->get($sync_type_id)->list($options);
    if (!$entities) {
      return;
    }

    // Finally, sync the entities one by one.
    foreach ($entities as $remote_entity) {
      $drupal_entity = $this->sync($remote_entity);
    }

    // Finally save the last synced time in the state.
    $this->set($last_synced_state_name, $options['to']);
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

    // If the entity's remote_changed value is the same as the remote's
    // last_changed value, we don't need to update anything.
    if ($drupal_entity->{$this->clientFactory->getRemoteChangedFieldName()}->value
      == $remote_entity->last_changed
    ) {
      return;
    }

    // Fetch the field mapping for this remote entity.
    $fields = $this->getFieldMapping($remote_entity, $drupal_entity);
    if (!$fields) {
      $message = 'There is no field mapping information for this entity.';
      $this->logger->error($message);
      return;
    }

    // Finally, do the field syncing.
    foreach ($fields as $field_info) {
      $drupal_entity = $this->importField(
        $remote_entity,
        $drupal_entity,
        $field_info
      );
    }

    // Save the Drupal entity now.
    $drupal_entity->set(
      $this->clientFactory->getRemoteIdFieldName(),
      $remote_id
    );
    $drupal_entity->set(
      $this->clientFactory->getRemoteChangedFieldName(),
      $remote_entity->last_changed
    );
    $drupal_entity->save();

    return $drupal_entity;
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

    return $drupal_entity;
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
      ->getStorage($entity_mapping['type_id']);

    // Check if a Drupal entity exists for this entity.
    $results = $entity_storage
      ->getQuery()
      ->condition($this->clientFactory->getRemoteIdFieldName(), $remote_id)
      ->execute();
    if ($results) {
      return $entity_storage->load(reset($results));
    }

    // If this is entity doesn't exist in Drupal, create it.
    $drupal_entity = $entity_storage
      ->create([
        'type' => $this->clientFactory->getBundle(),
        'status' => TRUE,
      ]);
    // Save the entity.
    $drupal_entity->save();

    return $drupal_entity;
  }

  /**
   * Initialize the sync type.
   *
   * @param string $sync_type_id
   *   The ID of the entity sync type.
   */
  protected function initializeSyncType($sync_type_id) {
    // Get the config for this sync type.
    $this->config = $this->configFactory
      ->get('entity_sync.sync.' . $sync_type_id);

    // Set the remote ID field for this sync type.
    $remote_id_field_name =
      !empty($this->config->get('entity.remote_id_field'))
        ? $this->config->get('entity.remote_id_field')
        : 'sync_remote_id';
    $this->clientFactory->setRemoteIdFieldName($remote_id_field_name);

    // Set the remote changed field for this sync type.
    $remote_changed_field_name =
      !empty($this->config->get('entity.remote_changed_field'))
        ? $this->config->get('entity.remote_changed_field')
        : 'sync_remote_changed';
    $this->clientFactory->setRemoteChangedFieldName($remote_changed_field_name);

    // Set the bundle for this sync type. If not bundle is set, use the entity
    // type ID.
    $entity_info = $this->config->get('entity');
    $bundle = $entity_info['bundle'] ?: $entity_info['type_id'];
    $this->clientFactory->setBundle($bundle);
  }

  /**
   * Validate the sync type.
   */
  protected function validateSyncType() {
    // Ensure that the application is properly set for syncing the entity.
    if (!$this->appReady()) {
      $message = 'The application is not properly set up to sync this entity.';
      $this->logger->error($message);
      throw new \RuntimeException($message);
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
      $this->clientFactory->getRemoteIdFieldName(),
      $this->clientFactory->getRemoteChangedFieldName(),
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
    $field_storage = FieldStorageConfig::loadByName(
      $this->config->get('entity.type_id'),
      $field_name
    );
    if (
      !empty($field_storage)
      && in_array(
        $this->clientFactory->getBundle(),
        $field_storage->getBundles()
      )
    ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Forms the options for a request.
   *
   * @param array $options
   *   An array of options needed for the request.
   * @param string $last_synced_state_name
   *   The state name for the sync type and operation.
   *
   * @return array
   *   The array of options.
   */
  protected function getRequestOptions(
    array $options,
    $last_synced_state_name
  ) {
    // If the force_all option is set, remove the from and to and return that.
    if ($options['force_all']) {
      unset($options['from']);
      unset($options['to']);

      return $options;
    }


    // If 'from' and 'to' are not set in $options, set it now.
    // Get the last synced timestamp for this operation.
    $last_synced = $this->state->get($last_synced_state_name);
    if (!$options['from']) {
      // Set the 'from' to the last synced time.
      $options['from'] = $last_synced;
    }
    if (!$options['to']) {
      // Set the 'to' to the current time.
      $options['to'] = $this->time->getRequestTime();
    }

    return $options;
  }

}
