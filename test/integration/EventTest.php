<?php

/**
 * Greg\Integration\EventTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Integration;

use Greg;
use Greg\Event;

/**
 * Test case for the Event wrapper class
 *
 * @group integration
 */
class EventTest extends IntegrationTest {
  public function test_event_non_recurring() {
    $current_time = '2020-10-31 22:00:00';

    $start = '2020-11-01 13:30:00';
    $end   = '2020-11-01 15:00:00';

    $this->factory->post->create([
      'post_type'                => 'greg_event',
      'post_title'               => 'My Single Event',
      'meta_input'               => [
        'start'                  => $start,
        'end'                    => $end,
      ],
    ]);

    // Mock the current time; we want to resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertFalse($event->recurring());
    // TODO test all public API methods
  }

  public function test_event_recurring() {
    $current_time = '2020-10-31 22:00:00';

    $start      = '2020-11-01 13:30:00';
    $end        = '2020-11-01 15:00:00';
    $until      = '2020-11-08 13:30:00';
    $exceptions = ['2020-11-03', '2020-11-06'];

    $this->factory->post->create([
      'post_type'                => 'greg_event',
      'post_title'               => 'My Recurring Event',
      'meta_input'               => [
        'start'                  => $start,
        'end'                    => $end,
        'frequency'              => 'DAILY',
        'until'                  => $until,
      ],
    ]);

    // Mock the current time; we want to resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertTrue($event->recurring());
    // TODO test all public API methods
  }

  public function test_event_with_recurrences_and_exceptions() {
    $current_time = '2020-10-31 22:00:00';

    $start      = '2020-11-01 13:30:00';
    $end        = '2020-11-01 15:00:00';
    $until      = '2020-11-08 13:30:00';
    $exceptions = ['2020-11-03', '2020-11-06'];

    $this->factory->post->create([
      'post_type'                => 'greg_event',
      'post_title'               => 'My Recurring Event',
      'meta_input'               => [
        'start'                  => $start,
        'end'                    => $end,
        'frequency'              => 'DAILY',
        'until'                  => $until,
        'exceptions'             => $exceptions,
        'recurrence_description' => 'Daily except for, like, a couple times',
      ],
    ]);

    // Mock the current time; we want to resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertEquals('Daily except for, like, a couple times', $event->recurrence_description());
  }

  public function test_event_recurrences_description() {
    $current_time = '2020-10-31 22:00:00';

    $start      = '2020-11-01 13:30:00';
    $end        = '2020-11-01 15:00:00';
    $until      = '2020-11-08 13:30:00';
    $exceptions = ['2020-11-03', '2020-11-06'];

    $this->factory->post->create([
      'post_type'                => 'greg_event',
      'post_title'               => 'My Recurring Event',
      'meta_input'               => [
        'start'                  => $start,
        'end'                    => $end,
        'frequency'              => 'DAILY',
        'until'                  => $until,
        'exceptions'             => $exceptions,
        'recurrence_description' => '',
      ],
    ]);

    // Mock the current time; we want to resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertEquals(
      'daily, starting from Nov 1, 2020, until Nov 8, 2020',
      $event->recurrence_description()
    );
  }

  public function test_event_with_custom_meta_keys() {
    add_filter('greg/meta_keys', function() : array {
      return [
        'start'                  => 'my_start',
        'end'                    => 'my_end',
        'frequency'              => 'my_frequency',
        'until'                  => 'my_until',
        'exceptions'             => 'my_exceptions',
        'recurrence_description' => 'my_recurrence_description',
      ];
    });

    $start   = date_create_immutable('now')->modify('+24 hours');
    $end     = $start->modify('+25 hours');
    $until   = $start->modify('+1 week');
    $except1 = $start->modify('+48 hours');
    $except2 = $start->modify('+120 hours');

    $this->factory->post->create([
      'post_type'                => 'greg_event',
      'post_title'               => 'My Single Event',
      'meta_input'               => [
        'my_start'                  => $start->format('Y-m-d 00:00:00'),
        'my_end'                    => $end->format('Y-m-d 00:00:00'),
        'my_frequency'              => 'DAILY',
        'my_until'                  => $until->format('Y-m-d 00:00:00'),
        'my_exceptions'             => [$except1->format('Y-m-d 00:00:00'), $except2->format('Y-m-d 00:00:00')],
        'my_recurrence_description' => 'Daily except for, like, a couple times',
      ],
    ]);

    $event = Greg\get_events()[0];

    $this->assertEquals('My Single Event', $event->title());

    $this->assertEquals(
      'Daily except for, like, a couple times',
      $event->recurrence_description()
    );
  }
}
