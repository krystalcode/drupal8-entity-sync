<?php

namespace Drupal\entity_sync\PermissionProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Provides permissions for each entity_sync.sync operation.
 */
class Sync implements ContainerInjectionInterface {

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
   * Returns an array of permissions for entity_sync.sync.
   *
   * Builds permissions for Sync with entity type AND bundle.
   *
   * @return array
   *   The entity_sync.sync permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   *
   * @throws \InvalidConfigurationException
   */
  public function buildPermissions() {
    $permissions = [];

    $sync_names = $this->configFactory->listAll('entity_sync.sync.');

    // Loop through each entity_sync.sync configurations and create
    // corresponding permissions.
    foreach ($this->configFactory->loadMultiple($sync_names) as $sync) {
      $config = $sync->get($config_name);

      // Throw an exception if we cannot find a entity type id.
      if (!$config['entity']['type_id']) {
        throw new InvalidConfigurationException(
          "Entity type id should be defined for configuration entity_sync.sync"
        );
      }

      // Do not proceed if operations are not set for config.
      if (!is_array($config['operations'])) {
        continue;
      }

      $entity_type_id = $config['entity']['type_id'];
      $operations = $config['operations'];

      $entity_type_plural_label = $this->entityTypeManager
        ->getStorage($entity_type_id)
        ->getEntityType()
        ->getPluralLabel();

      // Entity type id is a required field, hence we build entity type
      // permissions.
      $permissions += $this->buildEntityTypePermissions(
        $entity_type_plural_label,
        $entity_type_id,
        $operations
      );

      // If a entity bundle is provided we add in the bundle permissions as
      // well.
      if ($config['entity']['bundle']) {
        $permissions += $this->buildEntityBundlePermissions(
          $entity_type_plural_label,
          $config['entity']['bundle'],
          $entity_type_id,
          $operations
        );
      }
    }

    return $permissions;
  }

  /**
   * Builds permissions for sync with entity type and bundle.
   *
   * @param string $entity_label
   *   The entity plural label.
   * @param string $bundle
   *   The bundle ID.
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $operations
   *   The operations array from config object.
   *
   * @return array
   *   The permissions array.
   */
  private function buildEntityBundlePermissions(
    $entity_label,
    $bundle,
    $entity_type_id,
    array $operations
  ) {
    $permissions = [];

    $bundle_label = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($bundle)
      ->label();

    // Loop through each entity sync operation and create permission.
    foreach ($operations as $operation) {
      $operation_id = $operation['id'];

      $permissions += [
        "entity_sync ${OPERATION_ID} ${BUNDLE} ${ENTITY_TYPE_ID}" => [
          'title' => $this->t('
            %bundle: Run the @operation_label operation on @entity_type_plural_label',
            [
              '%bundle' => $bundle_label,
              '@entity_type_plural_label' => $entity_label,
              '@operation_label' => $operation_id,
            ]
          ),
        ],
      ];
    }

    return $permissions;
  }

  /**
   * Builds permissions for sync with entity type.
   *
   * @param string $entity_label
   *   The entity plural label.
   * @param string $entity_type_id
   *   The entity type id.
   * @param array $operations
   *   The operations array from config object.
   *
   * @return array
   *   The permissions array.
   */
  private function buildEntityTypePermissions(
    $entity_label,
    $entity_type_id,
    array $operations
  ) {
    $permissions = [];

    // Loop through each entity sync operation and create permission.
    foreach ($operations as $operation) {
      $operation_id = $operation['id'];

      $permissions += [
        "entity_sync ${OPERATION_ID} ${ENTITY_TYPE_ID}" => [
          'title' => $this->t('
            Run the @operation_label operation on @entity_type_plural_label',
            [
              '@entity_type_plural_label' => $entity_label,
              '@operation_label' => $operation_id,
            ]
          ),
        ],
      ];
    }

    return $permissions;
  }

}
