<?php

namespace Drupal\Tests\entity_sync\Unit\Import;

use Drupal\entity_sync\Import\ManagerInterface;
use Drupal\entity_sync\Plugin\QueueWorker\ImportList as QueueWorker;
use Drupal\Tests\UnitTestCase;

use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\entity_sync\Plugin\QueueWorker\ImportList
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class ImportListQueueWorkerTest extends UnitTestCase {

  /**
   * Different values for the datetime filters.
   */
  private $datetimeValues = [
    'string_start' => '2020-05-22T00:00:00Z',
    'string_end' => '2020-05-27T23:59:59Z',
  ];

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
          'filters' => [
            'changed_start' => $this->datetimeValues['string_start'],
            'changed_end' => $this->datetimeValues['string_end'],
          ],
          'options' => [
            'limit' => 10,
            'client' => [
              'parameters' => [
                'key1' => 'value1',
                'key2' => 'value2',
              ],
            ],
          ],
        ],
        'sync_id' => 'user',
        'filters' => [
          'changed_start' => $this->datetimeValues['string_start'],
          'changed_end' => $this->datetimeValues['string_end'],
        ],
        'options' => [
          'limit' => 10,
          'client' => [
            'parameters' => [
              'key1' => 'value1',
              'key2' => 'value2',
            ],
          ],
        ],
      ],
      // No filters, no options.
      [
        'data' => [
          'sync_id' => 'user',
        ],
        'sync_id' => 'user',
        'filters' => [],
        'options' => [],
      ],
      // Only filters.
      [
        'data' => [
          'sync_id' => 'user',
          'filters' => [
            'changed_start' => $this->datetimeValues['string_start'],
            'changed_end' => $this->datetimeValues['string_end'],
          ],
        ],
        'sync_id' => 'user',
        'filters' => [
          'changed_start' => $this->datetimeValues['string_start'],
          'changed_end' => $this->datetimeValues['string_end'],
        ],
        'options' => [],
      ],
      // Only options.
      [
        'data' => [
          'sync_id' => 'user',
          'options' => [
            'limit' => 10,
            'client' => [
              'parameters' => [
                'key1' => 'value1',
                'key2' => 'value2',
              ],
            ],
          ],
        ],
        'sync_id' => 'user',
        'filters' => [],
        'options' => [
          'limit' => 10,
          'client' => [
            'parameters' => [
              'key1' => 'value1',
              'key2' => 'value2',
            ],
          ],
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
        ['filters' => []],
      ],
    ];
  }

  /**
   * Tests.
   */

  /**
   * Tests that the import is called with the data properly formatted.
   *
   * @covers ::processItem
   * @dataProvider validDataProvider
   */
  public function testProcessItemValid($data, $sync_id, $filters, $options) {
    $manager = $this->prophesize(ManagerInterface::class);
    $manager
      ->importRemoteList(
        $sync_id,
        $filters,
        $options
      )
      ->shouldBeCalledOnce();

    $queue_worker = $this->buildQueueWorker($manager);
    $queue_worker->processItem($data);
  }

  /**
   * Tests that the import is not called when invalid data are given.
   *
   * @covers ::processItem
   * @dataProvider invalidDataProvider
   */
  public function testProcessItemInvalid($data) {
    $this->expectException(\InvalidArgumentException::class);

    $manager = $this->prophesize(ManagerInterface::class);
    $manager
      ->importRemoteList(Argument::any())
      ->shouldNotBeCalled();

    $queue_worker = $this->buildQueueWorker($manager);
    $queue_worker->processItem($data);
  }

  /**
   * Helper methods for preparing objects and mock objects.
   */

  /**
   * Prepare the queue worker object.
   */
  private function buildQueueWorker($manager) {
    $configuration = [];
    $plugin_id = 'entity_sync_import_list';
    $plugin_definition = [
      'id' => $plugin_id,
      'class' => 'Drupal\entity_sync\Plugin\QueueWorker\ImportList',
      'provider' => 'entity_sync',
    ];

    return new QueueWorker(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $manager->reveal()
    );
  }

}
