<?php

// @TODO Add namespace

namespace Drupal\Tests\entity_sync\Unit;
use Drupal\Tests\UnitTestCase;
use Drupal\enity_sync;

/**
 * Skeleton functional test.
 *
 * @group testing_examples
 */
class SkeletonTest extends UnitTestCase {

  public function testOneEqualsOne() {
    $filters = [
      'fromTime' => strtotime('2019-01-20T23:33:53Z'),
      'toTime' => strtotime('2019-03-20T23:33:53Z'),
    ];
    $results = \Drupal::service('entity_sync.import.manager')
      ->importRemoteList('group__app_company', $filters);
    $this->assertEquals(
      1, 1
    );
    return;

  }

}
