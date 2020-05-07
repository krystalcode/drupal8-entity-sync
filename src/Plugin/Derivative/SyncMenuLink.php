<?php

namespace Drupal\entity_sync\Plugin\Derivative;

use Drupal\Core\Config\ConfigFactoryInterface;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivative class that provides the menu links for the Sync operations.
 */
class SyncMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates a ProductMenuLink instance.
   *
   * @param string $base_plugin_id
   *   The base plugin id.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    $base_plugin_id,
    ConfigFactoryInterface $config_factory
  ) {
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
      $base_plugin_id,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    // @I Move this to a generic place as loading sync configurations are used
    //    in serveral other places.
    //    type     : improvement
    //    priority : low
    //    labels   : refactoring
    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding route links.
    foreach ($this->configFactory->loadMultiple($sync_names) as $sync) {
      $config = $sync->get();

      // Throw an exception if we cannot find a entity type id.
      if (!$config['entity']['type_id']) {
        throw new InvalidConfigurationException(
          "Entity type id should be defined for configuration entity_sync.sync"
        );
      }

      foreach ($config['operations'] as $operation) {
        $operation_id = $operation['id'];

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
