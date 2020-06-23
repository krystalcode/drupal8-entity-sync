<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\entity_sync\Export\EntityManagerInterface;

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
    EntityManagerInterface $manager
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
   */
  public function processItem($data) {
    // Load the entity.
    $entity = $this
      ->entityTypeManager
      ->getStorage($data['entity_type_id'])
      ->load($data['entity_id']);
    if (!$entity) {
      return;
    }

    $this->manager->exportLocalEntity(
      $data['sync_id'],
      $entity
    );
  }

}
