<?php

namespace Drupal\Tests\entity_sync\Unit\Utility;

use Drupal\entity_sync\Utility\DateTime;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\entity_sync\Utility\DateTime
 * @group entity_sync
 */
class DateTimeTest extends UnitTestCase {

  /**
   * Test cases were all arguments provided are valid.
   */
  public function timeAfterTimeValidDataProvider() {
    return [
      // No maximum time given.
      // 0 given as the start time.
      [
        0,
        12,
        NULL,
        12,
      ],
      // Positive integer given as the start time.
      [
        12,
        12,
        NULL,
        24,
      ],
      // No interval given.
      // 0 given as the start time.
      [
        0,
        NULL,
        12,
        12,
      ],
      // Positive integer given as the start time.
      [
        12,
        NULL,
        12,
        12,
      ],
      // Both interval and maximum time given.
      // Interval-based new time equals with maximum time
      [
        0,
        12,
        12,
        12,
      ],
      // Interval-based new time less than maximum time.
      [
        0,
        12,
        24,
        12,
      ],
      // Interval-based new time greater than maximum time.
      [
        0,
        24,
        12,
        12,
      ],
    ];
  }

  /**
   * @covers ::timeAfterTime
   * @dataProvider timeAfterTimeValidDataProvider
   */
  public function testTimeAfterTimeValid(
    $start_time,
    $interval,
    $max_time,
    $expected_new_time
  ) {
    $new_time = DateTime::timeAfterTime($start_time, $interval, $max_time);
    $this->assertEquals($expected_new_time, $new_time);
  }

  /**
   * Test cases were all arguments provided are valid.
   */
  public function timeAfterTimeInvalidDataProvider() {
    return [
      // Invalid start time i.e. not a Unix-timestamp in integer format.
      [
        -1,
        12,
        12,
        12,
      ],
      [
        '12',
        12,
        12,
        12,
      ],
      // Invalid maximum time i.e. not a Unix-timestamp in integer format.
      [
        12,
        12,
        -1,
        12,
      ],
      [
        -1,
        12,
        '12',
        12,
      ],
      // Invalid interval i.e. not a positive integer in integer format.
      [
        12,
        -1,
        12,
        12,
      ],
      [
        12,
        '12',
        12,
        12,
      ],
      // Neither interval nor maximum time are given.
      [
        12,
        NULL,
        NULL,
        12,
      ],
    ];
  }

  /**
   * @covers ::timeAfterTime
   * @dataProvider timeAfterTimeInvalidDataProvider
   */
  public function testTimeAfterTimeInvalid(
    $start_time,
    $interval,
    $max_time
  ) {
    $this->expectException(\InvalidArgumentException::class);
    DateTime::timeAfterTime($start_time, $interval, $max_time);
  }

  /**
   * Tests different cases for timestamps that is valid as integer or string.
   */
  public function isTimestampDataProvider() {
    return [
      // Valid cases.
      // 0 as integer.
      [0, TRUE],
      // Positive integer as integer.
      [1248, TRUE],
      // 0 as string.
      ['0', TRUE],
      // Positive integer as string.
      ['1248', TRUE],

      // Invalid cases.
      // Negative integer as integer.
      [-1, FALSE],
      // Negative integer as string.
      ['-1', FALSE],
      // Not an integer.
      ['string', FALSE],
      // Not an integer as string starting with a valid timestamp.
      // We test this scenario because in PHP a string starting with an integer
      // is converted to that integer value when cast to integer type
      // e.g. `(int) '1248string' === 1248`.`
      ['0string', FALSE],
      ['1248string', FALSE],
    ];
  }

  /**
   * @covers ::isTimestamp
   * @dataProvider isTimestampDataProvider
   */
  public function testIsTimestamp($value, $expected_result) {
    $result = DateTime::isTimestamp($value);
    $this->assertEquals($expected_result, $result);
  }

  /**
   * Tests different cases for timestamps that is valid if integer.
   */
  public function isIntegerTimestampDataProvider() {
    return [
      // Valid cases.
      // 0 as integer.
      [0, TRUE],
      // Positive integer as integer.
      [1248, TRUE],

      // Invalid cases.
      // 0 as string.
      ['0', FALSE],
      // Positive integer as string.
      ['1248', FALSE],
      // Negative integer as integer.
      [-1, FALSE],
      // Negative integer as string.
      ['-1', FALSE],
      // Not an integer.
      ['string', FALSE],
      // Not an integer as string starting with a valid timestamp.
      // We test this scenario because in PHP a string starting with an integer
      // is converted to that integer value when cast to integer type
      // e.g. `(int) '1248string' === 1248`.`
      ['0string', FALSE],
      ['1248string', FALSE],
    ];
  }

  /**
   * @covers ::isIntegerTimestamp
   * @dataProvider isIntegerTimestampDataProvider
   */
  public function testIsIntegerTimestamp($value, $expected_result) {
    $result = DateTime::isIntegerTimestamp($value);
    $this->assertEquals($expected_result, $result);
  }

}
