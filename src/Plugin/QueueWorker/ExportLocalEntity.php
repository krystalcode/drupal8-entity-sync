<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\entity_sync\Export\ManagerInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for exporting a list of entities.
 *
 * @QueueWorker(
 *  id = "entity_sync_export_local_entity",
 *  title = @Translation("Export local sentity"),
 *  cron = {"time" = 60}
 * )
 */
class ExportLocalEntity extends QueueWorkerBase implements
  ContainerFactoryPluginInterface {

  /**
   * The Entity Sync export manager service.
   *
   * @var \Drupal\entity_sync\Export\ManagerInterface
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
   * @param \Drupal\entity_sync\Export\ManagerInterface $manager
   *   The Entity Sync export manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ManagerInterface $manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

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
      $container->get('entity_sync.export.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @I Add an option to be notified when the export is run
   *    type     : feature
   *    priority : normal
   *    labels   : export, entity
   */
  public function processItem($data) {
    $this->manager->exportLocalEntity(
      $data['sync_id'],
      $data['entity'],
      $data['context']
    );
  }

}