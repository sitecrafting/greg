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
 * TESTING STRATEGY:
 *
 * There are three cases we really care about.
 *
 * 1.) start meta_value is BETWEEN start_date and end_date filters
 * 2.) start_date filter is BETWEEN start meta_value and end meta_value
 * 3.) end/until meta_value is BETWEEN start_date and end_date filters
 *
 * Note that we don't include a "end_date filter BETWEEN start/end meta" case
 * because it's actually redundant.
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
      'relation'    => 'OR',
      [
        // event.start BETWEEN start/end filters
        'key'       => 'start',
        'value'     => ['2020-10-01 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
      [
        // start filter is BETWEEN event.start AND event.end/event.until
        'relation'  => 'AND',
        [
          // event.start <= start filter
          'key'     => 'start',
          'value'   => '2020-10-01 00:00:00',
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event.end/event.until > start filter
          'key'     => ['end', 'until'],
          'value'   => '2020-10-01 00:00:00',
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ],
      [
        // event.end/event.until BETWEEN start/end filters
        'key'       => ['end', 'until'],
        'value'     => ['2020-10-01 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
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
      'relation'    => 'OR',
      [
        'key'       => 'start',
        'value'     => ['2020-10-15 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
      [
        // start filter is BETWEEN event.start AND event.end/event.until
        'relation'  => 'AND',
        [
          // event.start <= start filter
          'key'     => 'start',
          'value'   => '2020-10-15 00:00:00',
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event.end/event.until > start filter
          'key'     => ['end', 'until'],
          'value'   => '2020-10-15 00:00:00',
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ],
      [
        'key'       => ['end', 'until'],
        'value'     => ['2020-10-15 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_outside_current_month() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'event_month'  => '2020-09',
    ]);

    $this->assertEquals([
      'relation'    => 'OR',
      [
        // event.start is BETWEEN first and last day of filterd month
        'key'       => 'start',
        'value'     => ['2020-09-01 00:00:00', '2020-09-30 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
      [
        // start filter is BETWEEN event.start AND event.end/event.until
        'relation'  => 'AND',
        [
          // event.start <= start filter
          'key'     => 'start',
          'value'   => '2020-09-01 00:00:00',
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event.end/event.until > start filter
          'key'     => ['end', 'until'],
          'value'   => '2020-09-01 00:00:00',
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ],
      [
        'key'       => ['end', 'until'],
        'value'     => ['2020-09-01 00:00:00', '2020-09-30 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
    ], $query->params()['meta_query'][0]);
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
      'relation'    => 'OR',
      [
        'key'       => 'start',
        'value'     => ['2020-10-03 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
      [
        // start filter is BETWEEN event.start AND event.end/event.until
        'relation'  => 'AND',
        [
          // event.start <= start filter
          'key'     => 'start',
          'value'   => '2020-10-03 00:00:00',
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event.end/event.until > start filter
          'key'     => ['end', 'until'],
          'value'   => '2020-10-03 00:00:00',
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ],
      // End filter defaults to end of the month in which start filter occurs.
      [
        'key'       => ['end', 'until'],
        'value'     => ['2020-10-03 00:00:00', '2020-10-31 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_start_and_end_dates() {
    $query = new EventQuery([
      'current_time' => '2020-10-15 16:20:00',
      'start_date'   => '2020-10-03',
      'end_date'     => '2020-11-03',
    ]);

    $this->assertEquals([
      'relation'    => 'OR',
      [
        'key'       => 'start',
        'value'     => ['2020-10-03 00:00:00', '2020-11-03 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
      [
        // start filter is BETWEEN event.start AND event.end/event.until
        'relation'  => 'AND',
        [
          // event.start <= start filter
          'key'     => 'start',
          'value'   => '2020-10-03 00:00:00',
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event.end/event.until > start filter
          'key'     => ['end', 'until'],
          'value'   => '2020-10-03 00:00:00',
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ],
      [
        'key'       => ['end', 'until'],
        'value'     => ['2020-10-03 00:00:00', '2020-11-03 23:59:59'],
        'compare'   => 'BETWEEN',
        'type'      => 'DATETIME',
      ],
    ], $query->params()['meta_query'][0]);
  }

  public function test_params_event_category_slug() {
    $query = new EventQuery([
      'current_time'   => '2020-10-15 16:20:00',
      'event_category' => 'dogs',
    ]);

    $this->assertEquals([
      [
        'taxonomy' => 'greg_event_category',
        'terms'    => ['dogs'],
        'field'    => 'slug',
      ],
    ], $query->params()['tax_query']);
  }

  public function test_params_event_category_id() {
    $query = new EventQuery([
      'current_time'   => '2020-10-15 16:20:00',
      'event_category' => 123,
    ]);

    $this->assertEquals([
      [
        'taxonomy' => 'greg_event_category',
        'terms'    => [123],
        'field'    => 'term_id',
      ],
    ], $query->params()['tax_query']);
  }

  public function test_params_event_category_slug_array() {
    $query = new EventQuery([
      'current_time'   => '2020-10-15 16:20:00',
      'event_category' => ['dogs', 'snakes'],
    ]);

    $this->assertEquals([
      [
        'taxonomy' => 'greg_event_category',
        'terms'    => ['dogs', 'snakes'],
        'field'    => 'slug',
      ],
    ], $query->params()['tax_query']);
  }

  public function test_params_event_category_id_array() {
    $query = new EventQuery([
      'current_time'   => '2020-10-15 16:20:00',
      'event_category' => [123, 345],
    ]);

    $this->assertEquals([
      [
        'taxonomy' => 'greg_event_category',
        'terms'    => [123, 345],
        'field'    => 'term_id',
      ],
    ], $query->params()['tax_query']);
  }

  public function test_params_event_category_mixed_array() {
    $query = new EventQuery([
      'current_time'   => '2020-10-15 16:20:00',
      'event_category' => ['dogs', 123],
    ]);

    $this->assertEquals([
      [
        'taxonomy' => 'greg_event_category',
        'terms'    => ['dogs', '123'],
        'field'    => 'slug',
      ],
    ], $query->params()['tax_query']);
  }
}
