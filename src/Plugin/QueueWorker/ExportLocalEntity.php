<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

// Rename it to prevent confusion with Drupal's entity manager service.
use Drupal\entity_sync\Export\EntityManagerInterface as ExportEntityManagerInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for exporting a local entity.
 *
 * @QueueWorker(
 *  id = "entity_sync_export_local_entity",
 *  title = @Translation("Export local entity"),
 *  cron = {"time" = 60}
 * )
 */
class ExportLocalEntity extends QueueWorkerBase implements
  ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity Sync export entity manager service.
   *
   * @var \Drupal\entity_sync\Export\EntityManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new export instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_sync\Export\EntityManagerInterface $manager
   *   The Entity Sync export entity manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ExportEntityManagerInterface $manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_sync.export.entity_manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Data should be an associative array with the following elements:
   * - sync_id: The ID of the synchronization that defines the export operation
   *   to run.
   * - entity_type_id: The type ID of the entity being exported.
   * - entity_id: The ID of the entity being exported.
   *
   * @throws \InvalidArgumentException
   *   When invalid or inadequate data are passed to the queue worker.
   * @throws \RuntimeException
   *   When no entity was found for the given data.
   *
   * @see \Drupal\entity_sync\Export\EntityManager::exportLocalEntity()
   *
   * @I Write tests for the export local entity queue worker
   *    type     : task
   *    priority : normal
   *    labels   : export, testing, queue
   */
  public function processItem($data) {
    $this->validateData($data);

    // Load the entity.
    $entity = $this
      ->entityTypeManager
      ->getStorage($data['entity_type_id'])
      ->load($data['entity_id']);

    if (!$entity) {
      throw new \RuntimeException(
        sprintf(
          'No "%s" entity with ID "%s" found to export.',
          $data['entity_type_id'],
          $data['entity_id']
        )
      );
    }

    $this->manager->exportLocalEntity(
      $data['sync_id'],
      $entity
    );
  }

  /**
   * Validates that the data passed to the queue item are valid.
   *
   * @param mixed $data
   *   The data.
   *
   * @throws \InvalidArgumentException
   *   When the data are invalid.
   */
  protected function validateData($data) {
    if (!is_array($data)) {
      throw new \InvalidArgumentException(
        sprintf(
          'Queue item data should be an array, %s given.',
          gettype($data)
        )
      );
    }

    if (empty($data['sync_id'])) {
      throw new \InvalidArgumentException(
        'The ID of the synchronization that defines the export must be given.'
      );
    }

    if (empty($data['entity_type_id'])) {
      throw new \InvalidArgumentException(
        'The type ID of the entity being exported must be given.'
      );
    }

    if (empty($data['entity_id'])) {
      throw new \InvalidArgumentException(
        'The ID of the entity being exported must be given.'
      );
    }
  }

}
