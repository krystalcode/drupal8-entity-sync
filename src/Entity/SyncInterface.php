<?php

namespace Drupal\entity_sync\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Defines the interface for synchronizations.
 */
interface SyncInterface extends
  ConfigEntityInterface,
  EntityDescriptionInterface {

  /**
   * Returns the settings related to the local entities.
   *
   * @return array|null
   *   The local entity settings, or NULL if no settings have been defined.
   */
  public function getLocalEntitySettings();

  /**
   * Returns the settings related to the remote resource.
   *
   * @return array|null
   *   The remote resource settings, or NULL if no settings have been defined.
   */
  public function getRemoteResourceSettings();

  /**
   * Returns the settings related to the operations.
   *
   * @return array|null
   *   The operations settings, or NULL if no settings have been defined.
   */
  public function getOperationsSettings();

  /**
   * Returns the field mapping settings.
   *
   * @return array|null
   *   The field mapping settings, or NULL if no settings have been defined.
   */
  public function getFieldMapping();

}
