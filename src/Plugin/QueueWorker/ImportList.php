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
   * Data should be an associative array with the following elements:
   * - sync_id: The ID of the synchronization that defines the import operation
   *   to run.
   * - filters: (Optional) An array of filters to pass to the import entity
   *   manager.
   * - options: (Optional) An array of options to pass to the import entity
   *   manager.
   *
   * @see \Drupal\entity_sync\Improt\Manager::importRemoteList()
   *
   * @I Add an option to be notified when the import is run
   *    type     : feature
   *    priority : normal
   *    labels   : import, list
   */
  public function processItem($data) {
    $this->validateData($data);

    $this->manager->importRemoteList(
      $data['sync_id'],
      $data['filters'] ?? [],
      $data['options'] ?? []
    );
  }

  /**
   * Validates that the data passed to the queue item are valid.
   *
   * We do not validate that the data array items are of the correct types; that
   * is the responsibility of the import entity manager.
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
        'The ID of the synchronization that defines the import must be given.'
      );
    }
  }

}
