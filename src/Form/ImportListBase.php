<?php

namespace Drupal\entity_sync\Form;

use Drupal\entity_sync\StateManagerInterface;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for manually initiating an import of a list of entities.
 *
 * Currently, this form needs to be extended to customize it as required for a
 * specific entity list import.
 *
 * @I Display information about the last run
 *    type     : improvement
 *    priority : normal
 *    labels   : operation, ux
 */
abstract class ImportListBase extends FormBase {

  /**
   * The Entity Sync state manager.
   *
   * @var \Drupal\entity_sync\StateManagerInterface
   */
  protected $stateManager;

  /**
   * The queue for importing a list of entities.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Gets details about the operation to be run upon form submission.
   *
   * @return array
   *   An array containing the following elements:
   *   - The ID of the synchronization configuration that defines the operation.
   *   - An array of filters that will be passed to the import entity manager.
   *   - An array of options that will be passed to the import entity manager.
   *   See \Drupal\entity_sync\Import\ManagerInterface::importRemoteLIst().
   */
  abstract protected function getOperation();

  /**
   * Constructs a new ImportListFormBase object.
   *
   * @param \Drupal\entity_sync\StateManagerInterface $state_manager
   *   The Entity Sync state manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   */
  public function __construct(
    StateManagerInterface $state_manager,
    QueueFactory $queue_factory
  ) {
    $this->stateManager = $state_manager;
    $this->queue = $queue_factory->get('entity_sync_import_list');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_sync.state_manager'),
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

    $form['import'] = [
      '#type' => 'submit',
      '#name' => 'import',
      '#value' => $this->t('Import'),
      '#weight' => 1,
    ];

    // Alterations for the case of managed operations.
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();
    if (!$this->stateManager->isManaged($sync_id, 'import_list')) {
      return $form;
    }
    if ($this->stateManager->isLocked($sync_id, 'import_list')) {
      $this->buildUnlock($form, $form_state);
    }
    else {
      $this->buildLock($form, $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] === 'unlock') {
      $this->validateUnlock($form, $form_state);
      return;
    }
    if ($triggering_element['#name'] === 'lock') {
      $this->validateLock($form, $form_state);
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] === 'unlock') {
      $this->submitUnlock($form, $form_state);
      return;
    }
    if ($triggering_element['#name'] === 'lock') {
      $this->submitLock($form, $form_state);
      return;
    }

    $this->submitImport($form, $form_state);
  }

  /**
   * Builds the form elements for unlocking the operation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildUnlock(
    array &$form,
    FormStateInterface $form_state
  ) {
    $form['unlock_help'] = [
      '#markup' => '<p><strong>' . $this->t(
        'This operation is currently in locked state.'
      ) . '</strong><br />' . $this->t(
        'This is most likely happening because the operation is currently
        running, because it was manually locked, or because of an error. If you
        queue another import, it will run after the one currently running has
        finished or when the operation is unlocked again in the case it was
        manually locked. If you are sure that an error has caused the operation
        to erroneously stay in a locked state, you can use the button below to
        unlock it. If you unlock an operation that is locked because it is
        currently running (instead of being locked due to an error), you may
        cause the operation to run twice concurrently and that can cause
        problems in some cases. To avoid that, make sure that the operation is
        locked because of an error by looking at the logs, or contact the
        website administrator.'
      ) . '</p>',
    ];

    $form['unlock'] = [
      '#type' => 'submit',
      '#name' => 'unlock',
      '#value' => $this->t('Unlock'),
      '#weight' => 2,
    ];
  }

  /**
   * Builds the form elements for locking the operation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildLock(
    array &$form,
    FormStateInterface $form_state
  ) {
    $form['lock_help'] = [
      '#markup' => '<p>' . $this->t(
        'This operation is currently in unlocked state. You can lock the
        operation if you wish to prevent it from running until it is manually
        unlocked again.'
      ) . '</p>',
    ];

    $form['lock'] = [
      '#type' => 'submit',
      '#name' => 'lock',
      '#value' => $this->t('Lock'),
      '#weight' => 2,
    ];
  }

  /**
   * Validates the form when submitted by the `unlock` button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateUnlock(array &$form, FormStateInterface $form_state) {
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();

    if (!$this->stateManager->isManaged($sync_id, 'import_list')) {
      $form_state->setErrorByName(
        'submit',
        $this->t('Cannot unlock an unmanaged operation.')
      );
      return;
    }

    if (!$this->stateManager->isLocked($sync_id, 'import_list')) {
      $form_state->setErrorByName(
        'submit',
        $this->t('The operation is already unlocked.')
      );
    }
  }

  /**
   * Validates the form when submitted by the `lock` button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateLock(array &$form, FormStateInterface $form_state) {
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();

    if (!$this->stateManager->isManaged($sync_id, 'import_list')) {
      $form_state->setErrorByName(
        'submit',
        $this->t('Cannot lock an unmanaged operation.')
      );
      return;
    }

    if ($this->stateManager->isLocked($sync_id, 'import_list')) {
      $form_state->setErrorByName(
        'submit',
        $this->t(
          'The operation is already locked. There is a chance that the operation
          started running by the system or by somebody else after you loaded
          this form but before you submitted it, and that put it in a locked
          state. Please try again later when the current import has completed.'
        )
      );
    }
  }

  /**
   * Submission handler when the form is submitted by the `import` button.
   *
   * Queues the operation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function submitImport(
    array &$form,
    FormStateInterface $form_state
  ) {
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();

    // Add the users import to the queue.
    $this->queue->createItem([
      'sync_id' => $sync_id,
      'filters' => $filters,
      'options' => $options,
    ]);

    $this->messenger()->addMessage(
      $this->t('The import has been successfully queued.')
    );
  }

  /**
   * Submission handler when the form is submitted by the `import` button.
   *
   * Unlocks the operation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function submitUnlock(
    array &$form,
    FormStateInterface $form_state
  ) {
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();

    $this->stateManager->unlock($sync_id, 'import_list');

    $this->messenger()->addMessage(
      $this->t('The operation has been successfully unlocked.')
    );
  }

  /**
   * Submission handler when the form is submitted by the `import` button.
   *
   * Locks the operation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function submitLock(
    array &$form,
    FormStateInterface $form_state
  ) {
    $sync_id = $filters = $options = NULL;
    [$sync_id, $filters, $options] = $this->getOperation();

    $this->stateManager->lock($sync_id, 'import_list');

    $this->messenger()->addMessage(
      $this->t('The operation has been successfully locked.')
    );
  }

}
