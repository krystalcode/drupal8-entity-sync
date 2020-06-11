<?php

namespace Drupal\Tests\entity_sync\Unit\State;

use Drupal\entity_sync\Commands\State as Command;
use Drupal\entity_sync\StateManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\entity_sync\Commands\State
 * @group entity_sync
 */
class CommandsTest extends UnitTestCase {

  /**
   * Tests the state manager is properly called to unset the state.
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

}
