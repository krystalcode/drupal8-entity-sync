<?php

namespace Drupal\entity_sync\Utility;

/**
 * Utility methods that provide functionality related to dates and times.
 */
class DateTime {

  /**
   * Calculates a time as a Unix-timestamp based on the given parameters.
   *
   * - If only an interval is given, the calculated time is the start time plus
   *   the interval.
   * - If only a maximum time is given, the calculated time is the maximum time.
   * - If both an interval and a maximum time are given, the calculated time is
   *   the minimum of the maximum time and the start time plus the interval
   *   time.
   *
   * @param int $start_time
   *   The time in Unix-timestamp format from which to calculate the new time.
   * @param int $interval
   *   The difference between the new time and the start time in number of
   *   seconds.
   * @param int $max_time
   *   The maximum that the new time can be in Unix-timestamp format.
   *
   * @return int
   *   The calculated new time in Unix-timestamp format.
   *
   * @I Consider using ISO-8061 format for all times
   *    type     : task
   *    priority : low
   *    labels   : architecture
   */
  public static function timeAfterTime(
    $start_time,
    $interval = NULL,
    $max_time = NULL
  ) {
    // Validation.
    if (!self::isIntegerTimestamp($start_time)) {
      throw new \InvalidArgumentException(
        sprintf(
          'The start time must be a valid Unix-timestamp i.e. 0 or positive integer, "%s" of type "%s" given.',
          $start_time,
          gettype($start_time)
        )
      );
    }
    if ($interval !== NULL && !(is_int($interval) && $interval > 0)) {
      throw new \InvalidArgumentException(
        sprintf(
          'The maximum interval must be a positive integer, "%s" of type "%s" given.',
          $interval,
          gettype($interval)
        )
      );
    }
    if ($max_time !== NULL && !self::isIntegerTimestamp($max_time)) {
      throw new \InvalidArgumentException(
        sprintf(
          'The maximum time must be a valid Unix-timestamp i.e. 0 or positive integer, "%s" of type "%s" given.',
          $max_time,
          gettype($max_time)
        )
      );
    }
    if ($interval === NULL && $max_time === NULL) {
      throw new \InvalidArgumentException(
        'An interval and/or a maximum time must be provided, none given.'
      );
    }

    // If we are not given an interval, the new time is the maximum time.
    if ($interval === NULL) {
      return $max_time;
    }

    // If we are not given a maximum time, the new time is determined by the
    // interval.
    $new_time = $start_time + $interval;
    if ($max_time === NULL) {
      return $new_time;
    }

    // Otherwise, the new time is the minimum of the maximum time and the time
    // determined by the interval.
    if ($new_time > $max_time) {
      $new_time = $max_time;
    }

    return $new_time;
  }

  /**
   * Checks whether the given value is a Unix timestamp.
   *
   * A Unix timestamp is essentially any positive integer, or 0.
   *
   * @param int|string $value
   *   The value to check.
   *
   * @return bool
   *   Whether the value is a Unix timestamp.
   */
  public static function isTimestamp($value) {
    // If the value is a positive integer, it is a valid timestamp.
    if (is_int($value) && $value >= 0) {
      return TRUE;
    }

    // If the value is or can be converted to a string, all of its characters
    // should be numeric.
    if (ctype_digit((string) $value)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks whether the given integer value is a Unix timestamp.
   *
   * A Unix timestamp is essentially any positive integer, or 0.
   *
   * The difference with the `isTimestamp` method is that this method expects
   * the value to be an integer; a timestamp formatted as string won't be
   * considered a valid timestamp.
   *
   * @param int $value
   *   The value to check.
   *
   * @return bool
   *   Whether the value is a Unix timestamp.
   */
  public static function isIntegerTimestamp($value) {
    // If the value is a positive integer, it is a valid timestamp.
    if (is_int($value) && $value >= 0) {
      return TRUE;
    }

    return FALSE;
  }

}
