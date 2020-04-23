<?php

namespace Drupal\entity_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing a single entity.
 */
class ImportEntityFormBase extends FormBase {

  /**
   * The queue worker.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs a entity sync form object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(
    QueueFactory $queue_factory
  ) {
    $this->queue = $queue_factory->get('entity_sync_import');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_sync_import_entity_base_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['sync'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync Entity'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /**
     * @I Add proper data for performing the sync, and add proper messages.
     *    type     : improvement
     *    priority : normal
     *    labels   : company_sync
     */
    $this->queue->createItem();
  }

}
