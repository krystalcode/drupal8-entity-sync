<?php
/**
 * @file
 * Contains \Drupal\app_user\Plugin\QueueWorker\ExportUpdatedornewusers.
 */
namespace Drupal\Learning\Plugin\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
/**
 * Processes Tasks for Learning.
 *
 * @QueueWorker(
 *   id = "export_updatedornewusers",
 *   title = @Translation("Export Users saved or created in Drupal to the sync_api"),
 *   cron = {"time" = 60}
 * )
 */
class ExportUpdatedornewusers extends QueueWorkerBase {
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /*$mailManager = \Drupal::service('plugin.manager.mail');
    $params = $data;
    $mailManager->mail('learning', 'email_queue', $data['email'], 'en', $params , $send = TRUE);*/
  }
}
