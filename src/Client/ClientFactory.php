<?php

namespace Drupal\entity_sync\Client;

use Drupal\entity_sync\Exception\InvalidConfigurationException;

use Drupal\Core\Config\ConfigFactoryInterface;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Factory for generating clients.
 */
class ClientFactory implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The configuration factory that will be used to load the sync type.
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
   * Returns an initialized client for the requested sync type.
   *
   * @param string $sync_type_id
   *   The ID of the sync type that we will be performing operations for.
   *
   * @return \Drupal\entity_sync\Client\ClientInterface
   *   The initialized client.
   *
   * @throws \Drupal\entity_sync\Exception\InvalidConfigurationException
   */
  public function get($sync_type_id) {
    $sync_type = $this->configFactory->get(
      'entity_sync.sync.' . $sync_type_id
    );

    // Check that the sync type exists.
    if ($sync_type->isNew()) {
      throw new \InvalidArgumentException(
        sprintf(
          'There is no known entity sync type "%s"',
          $sync_type_id
        )
      );
    }

    // Check if a service has been defined for this sync type.
    $client_config = $sync_type->get('remote_resource.client');
    if (empty($client_config['type']) || $client_config['type'] !== 'service') {
      throw new InvalidConfigurationException(
        sprintf(
          'The entity sync type "%s" does not define a service that provides the remote resource client.',
          $sync_type_id
        )
      );
    }

    // Check if the sync type defines a service.
    if (empty($client_config['service'])) {
      throw new InvalidConfigurationException(
        sprintf(
          'The entity sync type "%s" does not define a service that provides the remote resource client.',
          $sync_type_id
        )
      );
    }

    // Check that the service implements the ClientInterface.
    $client = $this->container->get($client_config['service']);
    if (!$client instanceof ClientInterface) {
      throw new InvalidConfigurationException(
        sprintf(
          'The entity sync type "%s" must implement a service that is an instance of the \Drupal\entity_sync\Client\ClientInterface.',
          $sync_type_id
        )
      );
    }

    return $client;
  }

}
