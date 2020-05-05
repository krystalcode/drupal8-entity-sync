<?php

namespace Drupal\entity_sync\PermissionProvider;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides permissions for each entity_sync.sync operation.
 */
class ConfigPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of permissions for entity_sync.sync configs.
   *
   * @return array
   *   The entity_sync.sync permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function getPermissions() {
    $permissions = [];

    $config_factory = \Drupal::configFactory();

    // Loop through each entity_sync.sync configurations and create
    // corresponding permissions.
    foreach (
      $config_factory->listAll('entity_sync.sync.') as $config_name
    ) {
      $config = $config_factory->get($config_name)->get();

      // Do not proceed if operations are not set for config.
      if (!is_array($config['operations'])) {
        continue;
      }

      $bundle = $config['entity']['bundle'];
      $type_id = $config['entity']['type_id'];
      $type_id_label = ucfirst($type_id);

      // Loop through each entity sync operation and create permission.
      foreach ($config['operations'] as $operation) {
        $operation_id = $operation['id'];

        $permissions += [
          "$type_id $bundle $operation_id" => [
            'title' => $this->t(
              '%type_id_label: @bundle @operation',
              [
                '%type_id_label' => $type_id_label,
                '@bundle' => $bundle,
                '@operation' => $operation['label'],
              ]
            ),
          ],
        ];
      }
    }

    return $permissions;
  }

}
