<?php

namespace Drupal\entity_sync\Plugin\Derivative;

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
   * Creates a new Sync Block.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    $base_plugin_id
  ) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding permissions.
    foreach ($this->configFactory->loadMultiple($sync_names) as $sync_name => $sync) {
      $config = $sync->get();

      $operations = $config['operations'];

      // Loop through each entity sync operation and create blocks.
      foreach ($operations as $operation) {
        $derivative_id = $sync_name . '-' . $operation['id'] . '-' . $operation['label'];

        $this->derivatives[$derivative_id] = $base_plugin_definition;
        $this->derivatives[$derivative_id]['admin_label'] = t('Entity Sync Block:') . $operation['label'];
      }

    }

    return $this->derivatives;
  }

}
