<?php

namespace Drupal\Tests\entity_sync\Unit\State;

use Drupal\entity_sync\Commands\State as Command;
use Drupal\entity_sync\StateManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\entity_sync\Commands\State
 *
 * @group contrib
 * @group entity_sync
 * @group unit
 */
class CommandsTest extends UnitTestCase {

  /**
   * Tests that the state manager is properly called to unset the state.
   *
   * @covers ::unsetLastRun
   */
  public function testUnsetLastRun() {
    $sync_id = 'user';
    $operation = 'import_list';

    $state_manager = $this->prophesize(StateManagerInterface::class);
    $state_manager
      ->unsetLastRun($sync_id, $operation)
      ->shouldBeCalledOnce();

    $command = new Command($state_manager->reveal());
    $command->unsetLastRun($sync_id, $operation);
  }

  /**
   * Tests that the state manager is properly called to lock the operation.
   *
   * @covers ::lock
   */
  public function testLock() {
    $sync_id = 'user';
    $operation = 'import_list';

    $state_manager = $this->prophesize(StateManagerInterface::class);
    $state_manager
      ->lock($sync_id, $operation)
      ->shouldBeCalledOnce();

    $command = new Command($state_manager->reveal());
    $command->lock($sync_id, $operation);
  }

  /**
   * Tests that the state manager is properly called to unlock the operation.
   *
   * @covers ::unlock
   */
  public function testUnlock() {
    $sync_id = 'user';
    $operation = 'import_list';

    $state_manager = $this->prophesize(StateManagerInterface::class);
    $state_manager
      ->unlock($sync_id, $operation)
      ->shouldBeCalledOnce();

    $command = new Command($state_manager->reveal());
    $command->unlock($sync_id, $operation);
  }

}
