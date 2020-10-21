<?php

/**
 * Greg\Unit\EventQueryTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Unit;

use InvalidArgumentException;
use Greg\EventQuery;

/**
 * Test case for the core query logic within the public Greg API.
 *
 * @group unit
 */
class EventQueryTest extends BaseTest {
  public function test_params_default() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 00:00:00',
      // event_month defaults to current month
    ]);

    $this->assertEquals([
      // Include events that started after the first of the current month
      // after midnight.
      'key'     => 'start',
      'value'   => '2020-10-01 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
    $this->assertEquals([
      'relation' => 'OR',
      // Include events up to the end of the current month
      [
        'key'     => 'end',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
      [
        'key'     => 'until',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
    ], $query->params()['meta_query'][1]);
  }

  public function test_params_truncating_event_month() {
    $query = new EventQuery([
      // It's currently the 15th, and we don't want to include events from this
      // month that have already passed.
      'current_time'           => '2020-10-15 16:20:00',
      'truncate_current_month' => true,
      'event_month'            => '2020-10',
    ]);

    $this->assertEquals([
      'key'     => 'start',
      'value'   => '2020-10-15 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
    $this->assertEquals([
      'relation' => 'OR',
      // Include events up to the end of the current month
      [
        'key'     => 'end',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
      [
        'key'     => 'until',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
    ], $query->params()['meta_query'][1]);
  }

  public function test_params_outside_current_month() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'event_month'  => '2020-09',
    ]);

    $this->assertEquals([
      // Include events that started the first of start_date's month
      // at midnight, or later.
      'key'     => 'start',
      'value'   => '2020-09-01 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
    $this->assertEquals([
      'relation' => 'OR',
      // Include events up to the end of the current month
      [
        'key'     => 'end',
        'value'   => '2020-09-30 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
      [
        'key'     => 'until',
        'value'   => '2020-09-30 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
    ], $query->params()['meta_query'][1]);
  }

  public function test_params_garbage_current_time() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid date string: GARBAGE');

    $query = new EventQuery([
      'current_time' => 'GARBAGE',
    ]);
  }

  public function test_params_garbage_event_month() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid date string: GARBAGE');

    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'event_month'  => 'GARBAGE',
    ]);
  }

  public function test_params_garbage_start_date() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid date string: GARBAGE');

    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'start_date'   => 'GARBAGE',
    ]);
  }

  public function test_params_garbage_end_date() {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid date string: GARBAGE');

    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'end_date'     => 'GARBAGE',
    ]);
  }

  public function test_params_start_date() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'start_date'   => '2020-10-03',
    ]);

    $this->assertEquals([
      'key'     => 'start',
      'value'   => '2020-10-03 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
    $this->assertEquals([
      'relation' => 'OR',
      // Include events up to the end of the current month
      [
        'key'     => 'end',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
      [
        'key'     => 'until',
        'value'   => '2020-10-31 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
    ], $query->params()['meta_query'][1]);
  }

  public function test_params_start_and_end_dates() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'start_date'   => '2020-10-03',
      'end_date'     => '2020-11-03',
    ]);

    $this->assertEquals([
      'key'     => 'start',
      'value'   => '2020-10-03 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
    $this->assertEquals([
      'relation' => 'OR',
      // Include events up to the end of the current month
      [
        'key'     => 'end',
        'value'   => '2020-11-03 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
      [
        'key'     => 'until',
        'value'   => '2020-11-03 23:59:59',
        'compare' => '<=',
        'type'    => 'DATETIME',
      ],
    ], $query->params()['meta_query'][1]);
  }
}
