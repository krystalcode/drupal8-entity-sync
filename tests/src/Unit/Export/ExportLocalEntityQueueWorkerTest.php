<?php

namespace Drupal\Tests\entity_sync\Unit\Export;

use Drupal\entity_sync\Export\EntityManagerInterface;
use Drupal\entity_sync\Plugin\QueueWorker\ExportLocalEntity as QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;

use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\entity_sync\Plugin\QueueWorker\ExportLocalEntity
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class ExportLocalEntityQueueWorkerTest extends UnitTestCase {

  /**
   * Data providers.
   */

  /**
   * Valid data that could be provided to the queue worker.
   */
  public function validDataProvider() {
    return [
      // Complete set of valid data.
      [
        'data' => [
          'sync_id' => 'user',
          'entity_type_id' => 'user',
          'entity_id' => 1001,
        ],
      ],
    ];
  }

  /**
   * Invalid data that could be provided to the queue worker.
   */
  public function invalidDataProvider() {
    return [
      // Data is not array.
      [
        'string',
      ],
      // Data is array but does not contain the sync ID.
      [
        'filters' => [],
      ],
      // Data is array and has the sync_id but does not contain the entity type
      // ID.
      [
        'sync_id' => 'user',
      ],
      // Data is array and has the sync_id and entity type ID but does not
      // contain the entity ID.
      [
        'sync_id' => 'user',
        'entity_type_id' => 'user',
      ],
    ];
  }

  /**
   * Tests.
   */

  /**
   * Tests that the export is called with the data properly formatted.
   *
   * @covers ::processItem
   * @dataProvider validDataProvider
   */
  public function testProcessItemValid($data) {
    $user = $this->prophesize(UserInterface::class);
    $entity_type_manager = $this->buildEntityTypeManager($user->reveal());
    $manager = $this->prophesize(EntityManagerInterface::class);
    $manager
      ->exportLocalEntity(
        $data['sync_id'],
        $user
      )
      ->shouldBeCalledOnce();

    $queue_worker = $this->buildQueueWorker($entity_type_manager, $manager);
    $queue_worker->processItem($data);
  }

  /**
   * Tests that the export is not called when invalid data are given.
   *
   * @covers ::processItem
   * @dataProvider invalidDataProvider
   */
  public function testProcessItemInvalid($data) {
    $this->expectException(\InvalidArgumentException::class);

    $entity_type_manager = $this->buildEntityTypeManager();
    $manager = $this->prophesize(EntityManagerInterface::class);
    $manager
      ->exportLocalEntity(Argument::any())
      ->shouldNotBeCalled();

    $queue_worker = $this->buildQueueWorker($entity_type_manager, $manager);
    $queue_worker->processItem($data);
  }

  /**
   * Tests that the export is not called if an entity can't be found.
   *
   * @covers ::processItem
   * @dataProvider validDataProvider
   */
  public function testEntityNotFound($data) {
    $this->expectException(\RuntimeException::class);

    $entity_type_manager = $this->buildEntityTypeManager();
    $manager = $this->prophesize(EntityManagerInterface::class);
    $manager
      ->exportLocalEntity(Argument::any())
      ->shouldNotBeCalled();

    $queue_worker = $this->buildQueueWorker($entity_type_manager, $manager);
    $queue_worker->processItem($data);
  }

  /**
   * Helper methods for preparing objects and mock objects.
   */

  /**
   * Prepare the entity type manager object.
   */
  private function buildEntityTypeManager($user = NULL) {
    $user_storage = $this->prophesize(UserStorageInterface::class);
    $user_storage->load(1001)->willReturn($user);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('user')
      ->willReturn($user_storage->reveal());

    return $entity_type_manager;
  }

  /**
   * Prepare the queue worker object.
   */
  private function buildQueueWorker($entity_type_manager, $manager) {
    $configuration = [];
    $plugin_id = 'entity_sync_export_local_entity';
    $plugin_definition = [
      'id' => $plugin_id,
      'class' => 'Drupal\entity_sync\Plugin\QueueWorker\ExportLocalEntity',
      'provider' => 'entity_sync',
    ];

    return new QueueWorker(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager->reveal(),
      $manager->reveal()
    );
  }

}
