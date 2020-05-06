<?php

namespace Drupal\entity_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for manually initiating an import of a specific entity.
 *
 * Currently, this form needs to be extended to customize it as required for a
 * specific entity import.
 */
class ImportBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_sync.import';
  }

  /**
   * {@inheritdoc}
   *
   * @I Display more information such as time of last import
   *    type     : improvement
   *    priority : normal
   *    labels   : import, get, ux
   * @I Pass the sync type ID as an argument to the form
   *    type     : improvement
   *    priority : normal
   *    labels   : import, get
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $label = NULL,
    array $config = []
  ) {
    $form['help'] = [
      '#markup' => '<p>' . $this->t(
        'Import the latest changes from the corresponding remote resource.'
      ) . '</p>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $label,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @I Run the import upon form submission
   *    type     : improvement
   *    priority : normal
   *    labels   : import, get
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage(
      $this->t('The import has been successfully executed.')
    );
  }

}
