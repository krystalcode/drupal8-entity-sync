<?php

namespace Drupal\entity_sync\Client;

/**
 * Defines the interface for remote resource clients.
 *
 * Remote resource clients will be used to perform operations on the remote
 * resource such as get the list of entities to import or update the remote
 * entity that we are exporting.
 *
 * Currently, clients need to be made available as services and identified in
 * the Sync's configuration so that the Client Factory will know how to generate
 * the client.
 *
 * @see \Drupal\entity_sync\Client\ClientFactory
 */
interface ClientInterface {

  /**
   * Gets the list of remote entities to import.
   *
   * @param array $filters
   *   An associative array of filters that determine which entities will be
   *   imported. Supported conditions are:
   *   - fromTime: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated after or at the given
   *     timestamp.
   *   - toTime: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated before or at the given
   *     timestamp.
   *
   * @return \Iterator|null
   *   An iterator containing the entities to import, or NULL if there are no
   *   entities to import for the given filters.
   *
   * @I Support paginated results
   *    type     : improvement
   *    priority : high
   *    labels   : import, operation, memory-consumption
   */
  public function importList(array $filters = []);

  /**
   * Gets the remote entity with the given primary ID.
   *
   * @param int|string $id
   *   The ID of the entity to get.
   *
   * @return object
   *   The remote entity object.
   */
  public function importEntity($id);

}
