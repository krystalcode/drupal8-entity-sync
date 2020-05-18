<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\entity_sync\Import\ManagerInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for importing a list of entities.
 *
 * @QueueWorker(
 *  id = "entity_sync_import_list",
 *  title = @Translation("Import List"),
 *  cron = {"time" = 60}
 * )
 */
class ImportList extends QueueWorkerBase implements
  ContainerFactoryPluginInterface {

  /**
   * The Entity Sync import manager service.
   *
   * @var \Drupal\entity_sync\Import\ManagerInterface
   */
  protected $manager;

  /**
   * Constructs a new ImportList instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_sync\Import\ManagerInterface $manager
   *   The Entity Sync import manager service.
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
      $container->get('entity_sync.import.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @I Add an option to be notified when the import is run
   *    type     : feature
   *    priority : normal
   *    labels   : import, list
   */
  public function processItem($data) {
    $this->manager->importRemoteList(
      $data['sync_id'],
      [],
      $data['context'] ? ['context' => $data['context']] : []
    );
  }

}
