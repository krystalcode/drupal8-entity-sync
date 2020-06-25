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
   *   - created_start: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created after or at the given timestamp.
   *   - created_end: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created before or at the given timestamp.
   *   - changed_start: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created or updated after or at the given
   *      timestamp.
   *   - changed_end: (Optional) A Unix timestamp that when set, should limit
   *     the remote entities to those created or updated before or at the given
   *     timestamp.
   * @param array $options
   *   An associative array of options. Supported options are:
   *   - parameters: (Optional) Additional parameters; it is up to the client to
   *     determine how these parameters will be used. An example is to include
   *     query parameters that will be added to the request.
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
  public function importList(array $filters = [], array $options = []);

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

  /**
   * Creates a new remote entity for the given local entity fields.
   *
   * @param array $fields
   *   An associative array containing the fields that will be created, keyed by
   *   the field name and containing the field value.
   *
   * @return object
   *   The parsed response.
   */
  public function create(array $fields);

  /**
   * Updates the remote entity with the given primary ID.
   *
   * @param int|string $id
   *   The ID of the remote entity that will be updated.
   * @param array $fields
   *   An associative array containing the fields that will be updated, keyed by
   *   the field name and containing the field value.
   *
   * @return object
   *   The parsed response.
   */
  public function update($id, array $fields);

}
