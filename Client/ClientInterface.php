<?php

namespace Drupal\entity_sync\Client;

/**
 * Provides the interface for all client implementations of API resources.
 *
 * @I Determine if this implementation and iterator we're using is sufficient
 *    type     : improvement
 *    priority : normal
 *    labels   : refactoring
 */
interface ClientInterface {

  /**
   * Gets the resource's list of main entities.
   *
   * This is called the resource's default entity list request or method.
   *
   * @param array $options
   *   An associative array of options. Supported options are:
   *   - page (int): The index of the page to get, if the resource supports
   *     paging.
   *   - limit (int): The number of items per page, if the resource supports
   *     paging.
   *
   * @return \Drupal\entity_sync\Client\IteratorInterface
   *   - A paging iterator containing the items.
   *
   * @throws \Drupal\entity_sync\Client\Exception\UnsupportedMethodException
   *   If the resource does not support the default entity get request.
   * @throws \InvalidArgumentException
   *   If options related to paging are set but the resource does not support
   *   paging for its default list method.
   */
  public function list(array $options = []);

  /**
   * Gets a list of item for a resource.
   *
   * It can be used instead of the `list` method for getting items from
   * endpoints other than resource's default list request.
   *
   * @param string $endpoint
   *   The endpoint to send the request to.
   * @param string $data_key
   *   The key of the response JSON element that contains the list data.
   * @param array $options
   *   An associative array of options. Supported options are:
   *   - paging (bool): Whether the endpoint supports paging or not.
   *   - page (int): The index of the page to get, if the resource supports
   *     paging.
   *   - limit (int): The number of items per page, if the resource supports
   *     paging.
   * @param array $query
   *   An associative array containing additional query parameters to add to
   *   the request.
   *
   * @return \Drupal\entity_sync\Client\IteratorInterface
   *   - A paging iterator containing the items.
   *
   * @throws \InvalidArgumentException
   *   If options related to paging are set but the endpoint does not support
   *   paging.
   */
  public function listByEndpoint(
    string $endpoint,
    string $data_key,
    array $options = [],
    array $query = []
  );

  /**
   * Gets the resource's main entity by its primary ID.
   *
   * This is called the resource's default entity get request or method.
   *
   * @param int|string $id
   *   The ID of the entity to get.
   *
   * @return object
   *   The parsed response.
   *
   * @throws \Drupal\entity_sync\Client\Exception\UnsupportedMethodException
   *   If the resource does not support the default entity get request.
   */
  public function get($id): object;

  /**
   * Sends a GET request to the client API.
   *
   * @param string $endpoint
   *   The endpoint to send the request to.
   * @param array $query
   *   An associative array containing the query parameters for the request.
   * @param array $headers
   *   An associative array containing the headers for the request.
   * @param array $options
   *   An associative array containing the options for the request. Supported
   *   options are all request options supported by Guzzle.
   * @param int $retry
   *   The number of the request retry that we are currently at. Should be
   *   normally left to the default (0, initial request); it will be
   *   incremented on every retry based on the configuration passed to the
   *   client.
   *
   * @return object
   *   The response as an \stdClass object.
   *
   * @throws \Drupal\entity_sync\Client\Exception\ClientException
   *   If the request was unsuccessful due to a client error.
   *
   * @see http://docs.guzzlephp.org/en/stable/request-options.html
   */
  public function getRequest(
    string $endpoint,
    array $query = [],
    array $headers = [],
    array $options = [],
    int $retry = 0
  ): object;

  /**
   * Returns whether the resource supports paging for its default entity list.
   *
   * @return bool
   *   Whether the resource supports paging for its default entity list
   *   request. Defaults to `false` because initially API resources did not
   *   support paging.
   */
  public function supportsPaging(): bool;

}
