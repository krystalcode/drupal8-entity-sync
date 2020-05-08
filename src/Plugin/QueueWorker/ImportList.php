<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
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
      $container->get('config.factory')
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
    if ($sync->get('manager_callback')) {
      call_user_func('manager_callback');
    }
    // Otherwise, we call the default importRemoteList() service.
    else {
      \Drupal::service('entity_sync.import.manager')
        ->importRemoteList($sync_id);
    }
  }

}
