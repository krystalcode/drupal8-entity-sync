<?php

namespace Drupal\entity_sync;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * The default sync manager.
 */
class Manager implements ManagerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new EntityPermissions object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory
  ) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllSyncConfig() {
    return $this->configFactory->loadMultiple(
      $this->configFactory->listAll('entity_sync.sync.')
    );
  }

}
