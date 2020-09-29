<?php

namespace Drupal\Tests\entity_sync\Unit\Config;

use Drupal\entity_sync\Config\Manager;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\entity_sync\TestTrait\FixturesTrait;
use Drupal\Tests\UnitTestCase;

use Prophecy\Argument;

/**
 * @I Use autoloading for loading test classes
 *    type     : task
 *    priority : low
 *    labels   : testing
 */
require_once __DIR__ . '/../../TestTrait/FixturesTrait.php';

/**
 * @coversDefaultClass \Drupal\entity_sync\Config\Manager
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class SyncManagerTest extends UnitTestCase {

  use FixturesTrait;

  /**
   * Data provider.
   */
  public function dataProvider() {
    return [
      // No filters and bundle is optional.
      [
        [
          'local_entity' => [
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
        ],
        [
          'user__operation_enabled',
          'user__operation_disabled',
          'user__operation_undefined',
          'node__operation_enabled',
        ],
      ],
      // Filter by entity type ID only and bundle is optional.
      [
        [
          'local_entity' => [
            'type_id' => 'user',
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
        ],
        [
          'user__operation_enabled',
          'user__operation_disabled',
          'user__operation_undefined',
        ],
      ],
      // Filter by entity type ID and bundle.
      [
        [
          'local_entity' => [
            'type_id' => 'node',
            'bundle' => [
              'id' => 'page',
              'optional' => TRUE,
            ],
          ],
        ],
        [
          'node__operation_enabled',
        ],
      ],
      // Filter by operation ID only and bundle is optional.
      [
        [
          'local_entity' => [
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
          'operation' => ['id' => 'import_list'],
        ],
        [
          'user__operation_enabled',
          'user__operation_disabled',
          'node__operation_enabled',
        ],
      ],
      // Filter by operation ID and status and bundle is optional.
      [
        [
          'local_entity' => [
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
          'operation' => [
            'id' => 'import_list',
            'status' => TRUE,
          ],
        ],
        [
          'user__operation_enabled',
          'node__operation_enabled',
        ],
      ],
      [
        [
          'local_entity' => [
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
          'operation' => [
            'id' => 'import_list',
            'status' => FALSE,
          ],
        ],
        [
          'user__operation_disabled',
        ],
      ],
      // Filter by entity type ID, bundle, operation ID and status and bundle is
      // optional.
      [
        [
          'local_entity' => [
            'type_id' => 'user',
            'bundle' => [
              'optional' => TRUE,
            ],
          ],
          'operation' => [
            'id' => 'import_list',
            'status' => TRUE,
          ],
        ],
        [
          'user__operation_enabled',
        ],
      ],
    ];
  }

  /**
   * Tests the sync manager properly returns the requested config objects.
   *
   * @covers ::getSyncs
   * @dataProvider dataProvider
   */
  public function testGetSyncs(
    array $filters,
    array $expected_sync_ids
  ) {
    $sync_ids = [
      'user__operation_enabled',
      'user__operation_disabled',
      'user__operation_undefined',
      'node__operation_enabled',
    ];
    $mock_syncs = $this->prophesizeSyncs($sync_ids);
    $config_factory = $this->prophesizeConfigFactory($sync_ids, $mock_syncs);

    $manager = new Manager($config_factory);
    $actual_syncs = $manager->getSyncs($filters);

    $expected_syncs = array_intersect_key(
      $mock_syncs,
      array_flip($expected_sync_ids)
    );

    $this->assertEquals($expected_syncs, $actual_syncs);
  }

  /**
   * Tests that the sync manager returns an empty array when there are no syncs
   * available, no matter what the given filters are.
   *
   * @covers ::getSyncs
   * @dataProvider dataProvider
   */
  public function testGetSyncsNoSyncsAvailable(
    array $filters,
    array $expected_sync_ids
  ) {
    $sync_ids = [];
    $mock_syncs = $this->prophesizeSyncs($sync_ids);
    $config_factory = $this->prophesizeConfigFactory($sync_ids, $mock_syncs);

    $manager = new Manager($config_factory);
    $actual_syncs = $manager->getSyncs($filters);

    $this->assertEquals([], $actual_syncs);
  }

  /**
   * Prepare the configuration factory mock object.
   */
  private function prophesizeConfigFactory(
    array $sync_ids,
    array $mock_syncs
  ) {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory
      ->listAll('entity_sync.sync.')
      ->willReturn($sync_ids)
      ->shouldBeCalledOnce();

    if (!$sync_ids) {
      return $config_factory->reveal();
    }

    foreach ($sync_ids as $sync_id) {
      $config_factory
        ->get($sync_id)
        ->willReturn($mock_syncs[$sync_id])
        ->shouldBeCalledOnce();
    }

    return $config_factory->reveal();
  }

  /**
   * Prepare the synchronization configuration mock objects for the given IDs.
   */
  private function prophesizeSyncs($sync_ids) {
    $syncs = [];
    foreach ($sync_ids as $sync_id) {
      $syncs[$sync_id] = $this->prophesizeSync($sync_id);
    }

    return $syncs;
  }

  /**
   * Prepare the synchronization configuration mock object for the given ID.
   */
  private function prophesizeSync($sync_id) {
    $something = $this;

    $sync = $this->prophesize(ImmutableConfig::class);
    $sync
      ->get(Argument::any())
      ->will(
        function (array $args) use ($something, $sync_id) {
          return $something->getFixtureDataProperty($args[0], $sync_id);
        }
      );

    return $sync->reveal();
  }

  /**
   * Other helper methods.
   */

  /**
   * The default fixture ID for the tests in this file.
   */
  private function defaultFixtureId() {
    return 'config_manager_get_syncs';
  }

}
