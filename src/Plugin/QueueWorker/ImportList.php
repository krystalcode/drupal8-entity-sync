<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\entity_sync\Import\Manager;

use Drupal\Core\Config\ConfigFactoryInterface;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity sync import manager service.
   *
   * @var \Drupal\entity_sync\Import\Manager
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\entity_sync\Import\Manager $manager
   *   The entity sync import manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    Manager $manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
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
      $container->get('config.factory'),
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
    $sync_id = $data['sync_id'];
    $sync = $this->configFactory->get('entity_sync.sync.' . $sync_id);

    // If the sync has defined a callback to use, use that.
    $callback = $sync->get('operations.import_list.manager_callback');
    if ($callback) {
      call_user_func($callback);
    }
    // Otherwise, we call the default importRemoteList() service.
    else {
      $this->manager->importRemoteList($sync_id);
    }
  }

}
