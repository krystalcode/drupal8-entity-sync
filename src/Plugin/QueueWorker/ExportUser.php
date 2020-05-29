<?php
/**
 * @file
 * Contains \Drupal\app_user\Plugin\QueueWorker\ExportUpdatedornewusers.
 */
namespace Drupal\entity_sync\Plugin\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
/*use Drupal\entity_sync\Export\ManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;*/

/**
 * Processes Tasks for Learning.
 *
 * @QueueWorker(
 *   id = "entity_sync_export_user",
 *   title = @Translation("Export Users saved or created in Drupal to the sync_api"),
 *   cron = {"time" = 60}
 * )
 */
class ExportUser extends QueueWorkerBase {
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /*$mailManager = \Drupal::service('plugin.manager.mail');
    $params = $data;
    $mailManager->mail('learning', 'email_queue', $data['email'], 'en', $params , $send = TRUE);*/
    \Drupal::logger('testingqueue')->info('In ExportUser');
    $this->manager->exportLocalEntity(
      $data['sync_id'],
      $data['context'],
    );
  }
}
