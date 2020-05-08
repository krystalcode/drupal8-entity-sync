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
          'There is no known entity sync "%s"',
          $sync_id
        )
      );
    }

    // Check if a service has been defined for this sync.
    $client_config = $sync->get('remote_resource.client');
    if (empty($client_config['type']) || $client_config['type'] !== 'service') {
      throw new InvalidConfigurationException(
        sprintf(
          'The entity sync "%s" does not define a service that provides the remote resource client.',
          $sync_id
        )
      );
    }

    // Check if the sync defines a service.
    if (empty($client_config['service'])) {
      throw new InvalidConfigurationException(
        sprintf(
          'The entity sync "%s" does not define a service that provides the remote resource client.',
          $sync_id
        )
      );
    }

    // Check that the service implements the ClientInterface.
    $client = $this->container->get($client_config['service']);
    if (!$client instanceof ClientInterface) {
      throw new InvalidConfigurationException(
        sprintf(
          'The "%s" service defined as the client for the entity sync "%s" must implement the \Drupal\entity_sync\Client\ClientInterface interface.',
          get_class($client),
          $sync_id
        )
      );
    }

    return $client;
  }

}
