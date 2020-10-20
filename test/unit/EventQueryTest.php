<?php

/**
 * Greg\Unit\EventQueryTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Unit;

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
      'key'     => 'start_date',
      'value'   => '2020-10-01 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
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
      'key'     => 'start_date',
      'value'   => '2020-10-15 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_outside_current_month() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'event_month'  => '2020-09',
    ]);

    $this->assertEquals([
      // Include events that started the first of start_date's month
      // at midnight, or later.
      'key'     => 'start_date',
      'value'   => '2020-09-01 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_garbage_event_month() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'event_month'  => 'GARBAGE', // this will fail over to current_time
    ]);

    $this->assertEquals([
      'key'     => 'start_date',
      'value'   => '2020-10-01 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_start_date() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'start_date'   => '2020-10-03',
    ]);

    $this->assertEquals([
      'key'     => 'start_date',
      'value'   => '2020-10-03 00:00:00',
      'compare' => '>=',
      'type'    => 'DATETIME',
    ], $query->params()['meta_query'][0]);
  }
}
