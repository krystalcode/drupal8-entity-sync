<?php

namespace Drupal\entity_sync;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Base manager class for the import/export operations.
 */
class SyncManagerBase {

  /**
   * Checks that the given operation is enabled for the given synchronization.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $sync
   *   The configuration object for the synchronization that defines the
   *   operation we are currently executing.
   * @param string $operation
   *   The operation to check.
   *
   * @return bool
   *   TRUE if the operation is enabled and supported, FALSE otherwise.
   */
  protected function operationSupported(ImmutableConfig $sync, $operation) {
    // @I Check that the provider supports the corresponding method as well
    //    type     : bug
    //    priority : normal
    //    labels   : import, operation, validation
    if (!$sync->get("operations.$operation.status")) {
      return FALSE;
    }

    return TRUE;
  }

}
