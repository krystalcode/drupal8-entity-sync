<?php

namespace Drupal\entity_sync\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class EntitySyncRoutes implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new EntityPermissions object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container
  ) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding routes.
    foreach ($this->configFactory->loadMultiple($sync_names) as $sync) {
      $config = $sync->get($config_name);
      $bundle = $config['entity']['bundle'];
      $entity_type_id = $config['entity']['type_id'];

      foreach ($config['operations'] as $operation) {
        $operation_id = $operation['id'];

        // Generate route id with the configuration id.
        $route_id = 'entity_sync.sync.' . $config['id'];

        // Generate route url based on the url path set in configuration.
        $route_url = '/admin/sync/entities' . $operation['url_path'];

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

        $routes[$route_id] = new Route(
          $route_url,
          [
            '_form' => $form_path,
            '_title' => $operation['label'],
            'label' => $operation['label'],
          ],
          [
            '_permission' => $permission,
          ],
          [
            'parameters' => [
              'label' => [
                'type' => 'string',
              ],
            ],
          ]
        );
      }
    }

    return $routes;
  }

}
