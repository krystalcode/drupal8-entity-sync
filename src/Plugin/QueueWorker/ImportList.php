<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

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
   * {@inheritdoc}
   *
   * @I Add proper logic for performing the import
   *    type     : bug
   *    priority : normal
   *    labels   : import, list
   * @I Add an option to be notified when the import is run
   *    type     : feature
   *    priority : normal
   *    labels   : import, list
   */
  public function processItem($data) {
  }

}
