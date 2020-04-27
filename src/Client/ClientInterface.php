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
 * the Sync Type's configuration so that the Client Factory will know how to
 * generate the client.
 *
 * @see \Drupal\entity_sync\Client\ClientFactory
 */
interface ClientInterface {

  /**
   * Get the list of remote entities to import.
   *
   * @param array $options
   *   An associative array of options. Supported options are:
   *   - from: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated after or at the given
   *     timestamp.
   *   - to: (Optional) A Unix timestamp that when set, should limit the
   *     remote entities to those created or updated before or at the given
   *     timestamp.
   *
   * @return \Iterator
   *   An iterator containing the entities to import.
   *
   * @I Support paginated results
   *    type     : improvement
   *    priority : high
   *    labels   : memory-consumption
   */
  public function list(array $options = []);

  /**
   * Gets the resource's main entity by its primary ID.
   *
   * @param int|string $id
   *   The ID of the entity to get.
   *
   * @return object
   *   The parsed response.
   */
  public function get($id): object;

}
