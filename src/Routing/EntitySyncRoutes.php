<?php

namespace Drupal\entity_sync\Routing;

use Drupal\entity_sync\ManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines Routes for each entity sync operation.
 *
 * Loop through the entity sync configurations and create route for each
 * operation defined.
 */
class EntitySyncRoutes implements ContainerInjectionInterface {

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
   * Constructs a new EntitySyncRoutes object.
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
    ContainerInterface $container
  ) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_sync.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    foreach ($this->syncManager->getAllSyncConfig() as $sync) {
      $config = $sync->get();

      // Throw an exception if we cannot find a entity type id.
      if (!$config['entity']['type_id']) {
        throw new InvalidConfigurationException(
          "Entity type id should be defined for configuration entity_sync.sync"
        );
      }

      $routes = $this->buildRoutes($config);

    }

    return $routes;
  }

  /**
   * Build route for each sync operation.
   *
   * We build route only for operations which have set route as required.
   *
   * @param array $config
   *   The sync config array.
   *
   * @return array
   *   The route array
   */
  private function buildRoutes(array $config) {
    $routes = [];

    $bundle = $config['entity']['bundle'];
    $entity_type_id = $config['entity']['type_id'];

    $route_operations = $this->syncManager->getSyncOperationsForRoute();

    foreach ($route_operations as $operation_id => $form_class) {
      $operation = $config['operations'][$operation_id];

      // Generate route id with the operation id.
      $route_id = 'entity_sync.sync.' . $operation_id;

      // Generate route url based on the url path set in configuration.
      $route_url = '/admin/sync/entities/' . $operation['url_path'];

      // If a bundle is set for the config we use the bundle and entity perm,
      // if not we use the entity type permission.
      $permission = "entity_sync ${operation_id} ${entity_type_id}";
      if ($bundle) {
        $permission = "entity_sync ${operation_id} ${bundle} ${entity_type_id}";
      }

      // Create routes by passing in label and configuration as parameters.
      $routes[$route_id] = new Route(
        $route_url,
        [
          '_form' => $form_class,
          '_title' => $operation['label'],
          'label' => $operation['label'],
          'config' => $config,
        ],
        [
          '_permission' => $permission,
        ],
        [
          'parameters' => [
            'label' => [
              'type' => 'string',
            ],
            'config' => [
              'type' => 'array',
            ],
          ],
        ]
      );
    }

    return $routes;
  }

}
