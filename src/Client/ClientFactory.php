<?php

namespace Drupal\entity_sync\Client;

use Drupal\entity_sync\Exception\InvalidConfigurationException;

use Drupal\Core\Config\ConfigFactoryInterface;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Factory for generating Entity Sync clients.
 *
 * @see \Drupal\entity_sync\Client\ClientInterface
 */
class ClientFactory implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The configuration factory that will be used to load the synchronization.
   *
   * @var array
   */
  protected $configFactory;

  /**
   * Constructs a new ClientFactory object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Returns an initialized client for the requested synchronization.
   *
   * @param string $sync_id
   *   The ID of the sync that we will be performing operations for.
   *
   * @return \Drupal\entity_sync\Client\ClientInterface
   *   The initialized client.
   *
   * @throws \Drupal\entity_sync\Exception\InvalidConfigurationException
   */
  public function get($sync_id) {
    $sync = $this->configFactory->get(
      'entity_sync.sync.' . $sync_id
    );

    // Check that the sync exists.
    if ($sync->isNew()) {
      throw new \InvalidArgumentException(
        sprintf(
          'There is no known entity synchronization "%s"',
          $sync_id
        )
      );
    }

    // Check if a service has been defined for this sync.
    $client_config = $sync->get('remote_resource.client');
    return $this->getByClientConfig($client_config, $sync_id);
  }

  /**
   * Returns an initialized client for the requested client configuration.
   *
   * @param array $client_config
   *   An associative array containing the client configuration. Supported
   *   elements are:
   *   - type: The type of the client; currently supported type is `service`.
   *   - service: The Drupal service that provides the client.
   * @param string|null $sync_id
   *   The ID of the sync that we will be performing operations for, if
   *   available.
   *
   * @return \Drupal\entity_sync\Client\ClientInterface
   *   The initialized client.
   *
   * @throws \Drupal\entity_sync\Exception\InvalidConfigurationException
   *   When the client configuration is invalid.
   */
  public function getByClientConfig(array $client_config, $sync_id = NULL) {
    if (empty($client_config['type']) || $client_config['type'] !== 'service') {
      throw new InvalidConfigurationException(
        $this->buildExceptionMessage(
          'The given client configuration does not define a service that provides the remote resource client.',
          $client_config,
          $sync_id
        )
      );
    }

    // Check if the config defines a service.
    if (empty($client_config['service'])) {
      throw new InvalidConfigurationException(
        $this->buildExceptionMessage(
          'The given client configuration does not define a service that provides the remote resource client.',
          $client_config,
          $sync_id
        )
      );
    }

    // Check that the service implements the ClientInterface.
    $client = $this->container->get($client_config['service']);
    if (!$client instanceof ClientInterface) {
      throw new InvalidConfigurationException(
        $this->buildExceptionMessage(
          'The "%s" service defined as the client for the given client configuration must implement the \Drupal\entity_sync\Client\ClientInterface interface.',
          $client_config,
          $sync_id,
          $client_config['service']
        )
      );
    }

    return $client;
  }

  /**
   * Prepares the given message so that it contains client config and sync info.
   *
   * @param string $message
   *   The message to prepare.
   * @param array $client_config
   *   An associative array containing the client configuration as defined in
   *   the synchronization schema.
   * @param string|null $sync_id
   *   The ID of the sync that we will be performing operations for, if
   *   available.
   * @param mixed $args
   *   Additional arguments to pass to `sprintf` for preparing the message.
   */
  protected function buildExceptionMessage(
    $message,
    array $client_config,
    $sync_id = NULL,
    ...$args
  ) {
    $params = [];

    if ($sync_id) {
      $message .= ' Sync ID: %s.';
      $params[] = $sync_id;
    }
    $message .= ' Client config: %s';
    $params[] = json_encode($client_config);

    return call_user_func_array(
      'sprintf',
      array_merge(
        [$message],
        $args,
        $params
      )
    );
  }

}
