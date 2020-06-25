<?php

namespace Drupal\entity_sync\Commands;

use Drupal\entity_sync\Export\EntityManagerInterface;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

use Drush\Commands\DrushCommands;

/**
 * Commands related to exports.
 */
class Export extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity Sync export entity manager.
   *
   * @var \Drupal\entity_sync\Export\EntityManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new Export object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_sync\Export\EntityManagerInterface $manager
   *   The Entity Sync export entity manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityManagerInterface $manager
  ) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->manager = $manager;
  }

  /**
   * Exports a local entity based on the given synchronization.
   *
   * @param string $sync_id
   *   The ID of the synchronization that defines the export.
   * @param string $entity_id
   *   The ID of the local entity to export. The entity type is not needed as it
   *   is defined in the synchronization configuration.
   *
   * @usage drush entity-sync:export-local-entity 1 "my_sync_id"
   *   Export the local entity with ID "1" as defined in the `export_entity`
   *   operation of the `my_sync_id` synchronization.
   *
   * @command entity-sync:export-local-entity
   *
   * @aliases esync-ele
   */
  public function exportLocalEntity($sync_id, $entity_id) {
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);
    $entity_type_id = $sync->get('entity.type_id');

    $entity = $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($entity_id);

    if (!$entity) {
      throw new \InvalidArgumentException(
        sprintf(
          'No entity of type "%s" and ID "%s" was found.',
          $entity_type_id,
          $entity_id
        )
      );
    }

    $this->manager->exportLocalEntity(
      $sync_id,
      $entity
    );
  }

}
