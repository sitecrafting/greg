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

    // Mock the current time; we want to compare resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertFalse($event->recurring());
    $this->assertEquals('November 1, 2020 1:30 pm', $event->start());
    $this->assertEquals('3:00 pm', $event->end());
    $this->assertEquals('November 1, 2020 1:30 pm - 3:00 pm', $event->range());
    $this->assertEquals('', $event->until());
    $this->assertEmpty($event->frequency());
    $this->assertEmpty($event->recurrence_description());
  }

  public function test_event_date_formatting() {
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

    // Mock the current time; we want to compare resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertEquals('Sunday, November 1st at 1:30pm', $event->start('l, F jS \a\t g:ia'));
    $this->assertEquals('3:00pm', $event->end('g:ia'));
    $this->assertEquals('11/1 1:30pm thru 3:00pm', $event->range('m/j g:ia', 'g:ia', ' thru '));
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

    // Mock the current time; we want to compare resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertTrue($event->recurring());
    $this->assertEquals('November 1, 2020 1:30 pm', $event->start());
    $this->assertEquals('3:00 pm', $event->end());
    $this->assertEquals('November 8, 2020', $event->until());
    $this->assertEquals('Daily', $event->frequency());
    $this->assertEquals(
      'daily, starting from Nov 1, 2020, until Nov 8, 2020',
      $event->recurrence_description()
    );
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

    // Mock the current time; we want to compare resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    $this->assertEquals('Daily except for, like, a couple times', $event->recurrence_description());
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

    $current_time = '2020-10-31 22:00:00';

    $start      = '2020-11-01 13:30:00';
    $end        = '2020-11-01 15:00:00';
    $until      = '2020-11-08 13:30:00';
    $exceptions = ['2020-11-03', '2020-11-06'];

    $this->factory->post->create([
      'post_type'                   => 'greg_event',
      'post_title'                  => 'My Recurring Event',
      'meta_input'                  => [
        'my_start'                  => $start,
        'my_end'                    => $end,
        'my_frequency'              => 'DAILY',
        'my_until'                  => $until,
        'my_exceptions'             => $exceptions,
        'my_recurrence_description' => 'Daily except for, like, a couple times',
      ],
    ]);

    // Mock the current time; we want to compare resulting date strings exactly.
    $event = Greg\get_events([
      'current_time' => $current_time,
    ])[0];

    // Calling corresponding methods should be exactly the same as if we had
    // used all the default meta_keys.
    $this->assertTrue($event->recurring());
    $this->assertEquals('My Recurring Event', $event->title());
    $this->assertEquals('November 1, 2020 1:30 pm', $event->start());
    $this->assertEquals('3:00 pm', $event->end());
    $this->assertEquals('November 8, 2020', $event->until());
    $this->assertEquals('Daily', $event->frequency());
    $this->assertEquals(
      'Daily except for, like, a couple times',
      $event->recurrence_description()
    );
  }
}
