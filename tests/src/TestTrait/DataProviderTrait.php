<?php

namespace Drupal\Tests\entity_sync\TestTrait;

/**
 * Provides methods for making it easier to work with multiple data providers.
 *
 * @I Support data provider validation methods
 *    type     : improvement
 *    priority : low
 *    labels   : testing
 */
trait DataProviderTrait {

  /**
   * Prepares all possible combinations of data from the given providers.
   *
   * @param string[] $providers
   *   An array of method names that act as data providers.
   *
   * @return array
   *   An array with all data combinations.
   */
  protected function combineDataProviders(array $providers) {
    $this->validateDataProviders($providers);

    $data = [];

    // Initialize with the first provider's data.
    $provider = $providers[0];
    unset($providers[0]);
    foreach ($this->{$provider}() as $data_instance) {
      $data[] = [$data_instance];
    }

    // Go through each additional providers and create all possible data
    // combinations.
    foreach ($providers as $provider) {
      $data_copy = $data;
      $data = [];

      foreach ($this->{$provider}() as $data_instance) {
        foreach ($data_copy as $data_item) {
          array_push($data_item, $data_instance);
          $data[] = $data_item;
        }
      }
    }

    return $data;
  }

  /**
   * Validates that all given providers exist.
   *
   * @param string[] $providers
   *   An array of method names that act as data providers.
   *
   * @throws \InvalidArgumentException
   *   When at least one data provider does not exist.
   */
  protected function validateDataProviders(array $providers) {
    foreach ($providers as $provider) {
      if (method_exists($this, $provider)) {
        continue;
      }

      throw new \InvalidArgumentException(
        sprintf('Unknown data provider %s.', $provider)
      );
    }
  }

}
