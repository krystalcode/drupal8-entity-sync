<?php

namespace Drupal\entity_sync\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Sync company queue worker.
 *
 * @QueueWorker(
 *  id = "entity_sync_import_list",
 *  title = @Translation("Import Entity List"),
 *  cron = {"time" = 30}
 * )
 */
class ImportEntityList extends QueueWorkerBase implements
  ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /**
     * @I Add proper logic for performing the import
     *    type     : improvement
     *    priority : normal
     *    labels   : entity_sync
     */
  }

}
