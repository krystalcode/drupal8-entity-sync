<?php

namespace Drupal\entity_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for manually initiating an import of a list of entities.
 *
 * Currently, this form needs to be extended to customize it as required for a
 * specific entity list import.
 */
abstract class ImportListBase extends FormBase {

  /**
   * The queue for importing a list of entities.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs a new ImportListFormBase object.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(QueueFactory $queue_factory) {
    $this->queue = $queue_factory->get('entity_sync_import_list');
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
    return 'entity_sync.import_list';
  }

  /**
   * {@inheritdoc}
   *
   * @I Display more information such as time of last import and import status
   *    type     : improvement
   *    priority : normal
   *    labels   : import, list, ux
   * @I Pass the sync type ID as an argument to the form
   *    type     : improvement
   *    priority : normal
   *    labels   : import, list
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['help'] = [
      '#markup' => '<p>' . $this->t(
        'Initiating an import will put it in a queue and will be processed on
        the background.'
      ) . '</p>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @I Queue a list import upon form submission
   *    type     : improvement
   *    priority : normal
   *    labels   : import, list, ux
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage(
      $this->t('The import has been successfully queued.')
    );
  }

}
