<?php

namespace Drupal\entity_sync;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Defines the default state manager interface.
 */
class StateManager implements StateManagerInterface {

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * Constructs a new StateManager object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory) {
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function get($sync_id, $operation) {
    $store = $this->keyValueFactory->get(
      $this->getCollectionName($sync_id)
    );
    return $store->get($operation, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRun($sync_id, $operation) {
    $store = $this->keyValueFactory->get(
      $this->getCollectionName($sync_id)
    );
    $value = $store->get($operation, []);

    return $value['last_run'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastRun($sync_id, $operation, $last_run) {
    $store = $this->keyValueFactory->get(
      $this->getCollectionName($sync_id)
    );
    $value = $store->get($operation, []);
    $value['last_run'] = $last_run;

    $store->set($operation, $value);
  }

  /**
   * Gets the collection name for the given synchronization ID.
   *
   * We store operation states in collections based on their synchronization ID.
   *
   * @param string $sync_id
   *   The ID of the entity synchronization that the operations belongs to.
   *
   * @return string
   *   The name of the collection.
   */
  protected function getCollectionName($sync_id) {
    return "entity_sync.state.$sync_id";
  }

}
