<?php

namespace Drupal\entity_sync\Plugin\Derivative;

use Drupal\entity_sync\ManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for entity sync operations.
 *
 * @see \Drupal\entity_sync\Plugin\Block\SyncBlock
 */
class SyncBlock extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The sync manager service.
   *
   * @var \Drupal\entity_sync\ManagerInterface
   */
  protected $syncManager;

  /**
   * Creates a new Sync Block.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\entity_sync\ManagerInterface $sync_manager
   *   The sync manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ManagerInterface $sync_manager
  ) {
    $this->configFactory = $config_factory;
    $this->syncManager = $sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    $base_plugin_id
  ) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_sync.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding permissions.
    foreach ($this->syncManager->getAllSyncConfig() as $sync) {
      $config = $sync->get();

      $operations = $config['operations'];

      $block_operations = $this->syncManager->getSyncOperationsForBlock();

      foreach ($block_operations as $operation_id => $form_class) {
        $operation = $config['operations'][$operation_id];

        $derivative_id = $sync_name . '-' . $operation['id'] . '-' . $operation['label'];

        $this->derivatives[$derivative_id] = $base_plugin_definition;
        $this->derivatives[$derivative_id]['admin_label'] = t('Entity Sync Block:') . $operation['label'];
      }

    }

    return $this->derivatives;
  }

}
