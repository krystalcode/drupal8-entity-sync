<?php

namespace Drupal\entity_sync\Commands;

use Drupal\entity_sync\StateManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Commands related to state management.
 */
class State extends DrushCommands {

  /**
   * The Entity Sync state manager.
   *
   * @var \Drupal\entity_sync\StateManagerInterface
   */
  protected $stateManager;

  /**
   * Constructs a new Import object.
   *
   * @param \Drupal\entity_sync\StateManagerInterface $state_manager
   *   The Entity Sync state manager.
   */
  public function __construct(StateManagerInterface $state_manager) {
    $this->stateManager = $state_manager;
  }

  /**
   * Unsets the last run state for the given synchronization and operation.
   *
   * @param string $sync_id
   *   The ID of the synchronization.
   * @param string $operation
   *   The operation.
   *
   * @usage drush entity-sync:unset-last-run "my_sync_id" "import_list"
   *   Unset the last run state for the  `import_list` operation of the
   *   `my_sync_id` synchronization.
   *
   * @command entity-sync:state-unset-last-run
   *
   * @aliases esync-sulr
   */
  public function unsetLastRun($sync_id, $operation) {
    $this->stateManager->unsetLastRun($sync_id, $operation);
  }

}
