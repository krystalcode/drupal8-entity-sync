<?php

namespace Drupal\entity_sync\Plugin\Derivative;

use Drupal\entity_sync\ManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu links for Sync operations.
 */
class SyncMenuLink extends DeriverBase implements ContainerDeriverInterface {

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
   * Creates a ProductMenuLink instance.
   *
   * @param string $base_plugin_id
   *   The base plugin id.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\entity_sync\ManagerInterface $sync_manager
   *   The sync manager.
   */
  public function __construct(
    $base_plugin_id,
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
      $base_plugin_id,
      $container->get('config.factory'),
      $container->get('entity_sync.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    foreach ($this->syncManager->getAllSyncConfig() as $sync) {
      $config = $sync->get();

      // Throw an exception if we cannot find a entity type id.
      if (!$config['entity']['type_id']) {
        throw new InvalidConfigurationException(
          "Entity type id should be defined for configuration entity_sync.sync"
        );
      }

      $route_operations = $this->syncManager->getSyncOperationsForRoute();
      foreach ($route_operations as $operation_id => $form_class) {
        $operation = $config['operations'][$operation_id];

        // Generate route id with the configuration id.
        $route_id = 'entity_sync.sync.' . $operation_id;

        $links[$route_id] = [
          'title' => $operation['label'],
          'route_name' => $route_id,
          'parent' => 'entity_sync.admin.entities',
        ] + $base_plugin_definition;

      }
    }

    return $links;
  }

}
