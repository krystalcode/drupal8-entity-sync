<?php

namespace Drupal\entity_sync\Routing;

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
   * Constructs a new EntitySyncRoutes object.
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
  public static function create(
    ContainerInterface $container
  ) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    // @I Move this to a generic place as loading sync configurations are used
    //    in serveral other places.
    //    type     : improvement
    //    priority : low
    //    labels   : refactoring
    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding routes.
    foreach ($this->configFactory->loadMultiple($sync_names) as $sync) {
      $config = $sync->get();

      // Throw an exception if we cannot find a entity type id.
      if (!$config['entity']['type_id']) {
        throw new InvalidConfigurationException(
          "Entity type id should be defined for configuration entity_sync.sync"
        );
      }

      $bundle = $config['entity']['bundle'];
      $entity_type_id = $config['entity']['type_id'];

      foreach ($config['operations'] as $operation) {
        $operation_id = $operation['id'];

        // Generate route id with the configuration id.
        $route_id = 'entity_sync.sync.' . $config['id'];

        // Generate route url based on the url path set in configuration.
        $route_url = '/admin/sync/entities/' . $operation['url_path'];

        // @I Move We need to use some better logic to fetch the form classes
        //    There would be export operations in the future.
        //    type     : improvement
        //    priority : high
        //    labels   : refactoring

        // If the operation is list operation we use the import list form, if
        // not we use the single import form.
        $form_path = 'Drupal\entity_sync\Form\ImportBase';
        if ($operation_id === 'importList') {
          $form_path = 'Drupal\entity_sync\Form\ImportListBase';
        }

        // If a bundle is set for the config we use the bundle and entity perm,
        // if not we use the entity type permission.
        $permission = "entity_sync ${OPERATION_ID} ${ENTITY_TYPE_ID}";
        if ($bundle) {
          $permission = "entity_sync ${OPERATION_ID} ${BUNDLE} ${ENTITY_TYPE_ID}";
        }

        // Create routes by passing in label and configuration as parameters.
        $routes[$route_id] = new Route(
          $route_url,
          [
            '_form' => $form_path,
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
    }

    return $routes;
  }

}
