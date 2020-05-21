<?php

namespace Drupal\Tests\entity_sync\Unit;

use Drupal\Component\Utility\NestedArray;
use Drupal\Tests\UnitTestCase;

use Symfony\Component\Yaml\Yaml;

/**
 * Provides a base class for export manager tests.
 */
abstract class ManagerTestBase extends UnitTestCase {

  /**
   * Returns a YAML converted to a PHP value, given, a file name.
   *
   * @param string $sync_case
   *   The particular sync case, ie. 'complete', 'operations_disabled', etc.
   * @param string $file_name
   *   The file name.
   *
   * @return mixed
   *   A YAML converted to a PHP value.
   */
  protected function getSync($sync_case, $file_name) {
    $syncs = Yaml::parse(
      file_get_contents(__DIR__ . '/../../fixtures/' . $file_name)
    );

    if (isset($syncs[$sync_case])) {
      return $syncs[$sync_case];
    }

    throw new \InvalidArgumentException(
      sprintf(
        'Synchronization case "%s" not found in the fixtures contain in "%s".',
        $sync_case,
        $file_name
      )
    );
  }

  /**
   * Returns a property from a sync array, given a key.
   *
   * @param string $file_name
   *   The file name.
   * @param string $sync_case
   *   The particular sync case, ie. 'complete', 'operations_disabled', etc.
   * @param string $key
   *   The property key to return.
   *
   * @return mixed
   *   Returns the property if it exists.
   */
  protected function getSyncProperty($file_name, $sync_case, $key = '') {
    $data = $this->getSync($sync_case, $file_name);

    if (empty($key)) {
      return $data;
    }

    $parts = explode('.', $key);
    if (count($parts) == 1) {
      return isset($data[$key]) ? $data[$key] : NULL;
    }
    else {
      $value = NestedArray::getValue($data, $parts, $key_exists);
      return $key_exists ? $value : NULL;
    }
  }

}
