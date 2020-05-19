<?php

namespace Drupal\Tests\entity_sync\Unit\Export;

use Drupal\entity_sync\Client\ClientFactory;
use Drupal\entity_sync\Client\ClientInterface;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a base class for export manager tests.
 */
abstract class ManagerTestBase extends UnitTestCase {

  /**
   * The sync ID.
   */
  const SYNC_ID = 'user';

  /**
   * The client factory mock object.
   *
   * @var \Drupal\entity_sync\Client\ClientFactory
   */
  protected $clientFactory;

  /**
   * The entity type manager mock object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher mock object.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger service mock object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The mock entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $localEntity;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    $this->mockClientFactory();
    $this->mockEntityTypeManager();
    $this->mockEventDispatcher();
    $this->mockLogger();
    $this->mockLocalEntity();
  }

  /**
   * Returns a YAML converted to a PHP value, given, a file name.
   *
   * @param string $file_name
   *   The file name.
   *
   * @return mixed
   *   A YAML converted to a PHP value.
   */
  protected function getConfigFixture($file_name) {
    return Yaml::parse(
      file_get_contents(__DIR__ . '/../../../fixtures/' . $file_name)
    );
  }

  /**
   * Returns a mocked config factory class, given a sync ID and config.
   *
   * @param string $sync_id
   *   The sync ID.
   * @param mixed $config
   *   A YAML converted to a PHP value.
   *
   * @return \PHPUnit\Framework\MockObject\MockBuilder
   *   A MockBuilder object for the ConfigFactory with the desired return
   *   values.
   */
  protected function getConfigFactory($sync_id, $config) {
    return $this->getConfigFactoryStub([
      'entity_sync.sync.' . $sync_id => $config
    ]);
  }

  /**
   * Mocks the client factory class.
   *
   * @throws \Drupal\entity_sync\Exception\InvalidConfigurationException
   */
  private function mockClientFactory() {
    $client = $this->prophesize(ClientInterface::class);
    $client = $client->reveal();
    $this->clientFactory = $this
      ->prophesize(ClientFactory::class);
    $this->clientFactory->get(self::SYNC_ID)->willReturn($client);
    $this->clientFactory = $this->clientFactory->reveal();
  }

  /**
   * Mocks the entity type manager class.
   */
  private function mockEntityTypeManager() {
    $this
      ->entityTypeManager = $this
      ->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager = $this->entityTypeManager->reveal();
  }

  /**
   * Mocks the event dispatcher class.
   */
  private function mockEventDispatcher() {
    $this->eventDispatcher = $this
      ->prophesize(EventDispatcherInterface::class);
    $this->eventDispatcher = $this->eventDispatcher->reveal();
  }

  /**
   * Mocks the logger class.
   */
  private function mockLogger() {
    $this->logger = $this
      ->prophesize(LoggerChannelInterface::class);
    $this->logger = $this->logger->reveal();
  }

  /**
   * Mocks the local entity.
   */
  private function mockLocalEntity() {
    $this->localEntity = $this
      ->prophesize(EntityInterface::class);
    $this->localEntity = $this->localEntity->reveal();
  }

}
