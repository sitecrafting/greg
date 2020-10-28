<?php

/**
 * Greg\Unit\CalendarTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Unit;

use Greg\Calendar;

/**
 * Test case for the core Calendar library, where we generate recurring event
 * post data.
 *
 * NOTE: Calendar is not responsible for querying events, only for translating
 * their recurrence rules into separate instances.
 *
 * @group unit
 */
class CalendarTest extends BaseTest {
  public function test_recurrences_with_unique_event() {
    // single event
    // 10am - 2pm
    // no recurrence (AKA unique)
    $unique = [
      'start' => '2020-02-03 10:00:00',
      'end'   => '2020-02-03 14:00:00',
      'title' => 'My Unique Event',
    ];

    $calendar = new Calendar([$unique]);

    $events = $calendar->recurrences();

    $this->assertEquals([$unique], $events);
  }

  public function test_recurrences_with_single_recurring() {
    // single event
    // 10am - 2pm
    // 3 recurrences (derived from start/until/frequency), weekly
    $recurring = [
      'start'                  => '2020-02-03 10:00:00',
      'end'                    => '2020-02-03 14:00:00',
      'title'                  => 'My Recurring Event',
      'recurrence'             => [
        'until'                => '2020-02-17 14:00:00',
        'frequency'            => 'Weekly',
      ],
      'recurrence_description' => 'Thrice',
    ];

    $calendar = new Calendar([$recurring]);

    $events = $calendar->recurrences();

    $this->assertEquals([
      [
        'start'                  => '2020-02-03 10:00:00',
        'end'                    => '2020-02-03 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'Thrice',
      ],
      [
        'start'                  => '2020-02-10 10:00:00',
        'end'                    => '2020-02-10 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'Thrice',
      ],
      [
        'start'                  => '2020-02-17 10:00:00',
        'end'                    => '2020-02-17 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'Thrice',
      ],
    ], $events);
  }

  public function test_recurrences_with_description() {
    // single event
    // 10am - 2pm
    // 3 recurrences (derived from start/until/frequency), weekly
    $recurring = [
      'start'       => '2020-02-03 10:00:00',
      'end'         => '2020-02-03 14:00:00',
      'title'       => 'My Recurring Event',
      'recurrence'  => [
        'until'     => '2020-02-17 14:00:00',
        'frequency' => 'Weekly',
      ],
    ];

    $calendar = new Calendar([$recurring], ['human_readable_format' => 'n/j/y']);

    $events = $calendar->recurrences();

    $this->assertEquals([
      [
        'start'                  => '2020-02-03 10:00:00',
        'end'                    => '2020-02-03 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'weekly, starting from 2/3/20, until 2/17/20',
      ],
      [
        'start'                  => '2020-02-10 10:00:00',
        'end'                    => '2020-02-10 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'weekly, starting from 2/3/20, until 2/17/20',
      ],
      [
        'start'                  => '2020-02-17 10:00:00',
        'end'                    => '2020-02-17 14:00:00',
        'title'                  => 'My Recurring Event',
        'recurrence'             => [
          'until'                => '2020-02-17 14:00:00',
          'frequency'            => 'Weekly',
        ],
        'recurrence_description' => 'weekly, starting from 2/3/20, until 2/17/20',
      ],
    ], $events);
  }

  public function test_default_human_readable_recurrence_description_format() {
    // single event
    // 10am - 2pm
    // 3 recurrences (derived from start/until/frequency), weekly
    $recurring = [
      'start'       => '2020-02-03 10:00:00',
      'end'         => '2020-02-03 14:00:00',
      'title'       => 'My Recurring Event',
      'recurrence'  => [
        'until'     => '2020-02-17 14:00:00',
        'frequency' => 'Weekly',
      ],
    ];

    $calendar = new Calendar([$recurring]);

    $events = $calendar->recurrences();

    $this->assertEquals(
      'weekly, starting from Feb 3, 2020, until Feb 17, 2020',
      $events[0]['recurrence_description']
    );
  }

  public function test_recurrences_with_multiple_recurring() {
    // multiple events:
    // - 2/3 - 2/5 10am - 3pm
    //   3 recurrences, daily
    // - 2/4 - 2/5 2pm - 7pm
    //   2 recurrences, daily
    $events = [
      [
        'start'                  => '2020-02-04 14:00:00',
        'end'                    => '2020-02-04 17:00:00',
        'title'                  => 'Two Times',
        'recurrence'             => [
          'until'                => '2020-02-05 17:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Twice.',
      ],
      [
        'start'                  => '2020-02-03 10:00:00',
        'end'                    => '2020-02-03 15:00:00',
        'title'                  => 'Three Times',
        'recurrence'             => [
          'until'                => '2020-02-05 15:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Thrice.',
      ],
    ];

    $calendar = new Calendar($events);

    $events = $calendar->recurrences();

    $this->assertEquals([
      [
        'start'                  => '2020-02-03 10:00:00',
        'end'                    => '2020-02-03 15:00:00',
        'title'                  => 'Three Times',
        'recurrence'             => [
          'until'                => '2020-02-05 15:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Thrice.',
      ],
      [
        'start'                  => '2020-02-04 10:00:00',
        'end'                    => '2020-02-04 15:00:00',
        'title'                  => 'Three Times',
        'recurrence'             => [
          'until'                => '2020-02-05 15:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Thrice.',
      ],
      [
        'start'                  => '2020-02-04 14:00:00',
        'end'                    => '2020-02-04 17:00:00',
        'title'                  => 'Two Times',
        'recurrence'             => [
          'until'                => '2020-02-05 17:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Twice.',
      ],
      [
        'start'                  => '2020-02-05 10:00:00',
        'end'                    => '2020-02-05 15:00:00',
        'title'                  => 'Three Times',
        'recurrence'             => [
          'until'                => '2020-02-05 15:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Thrice.',
      ],
      [
        'start'                  => '2020-02-05 14:00:00',
        'end'                    => '2020-02-05 17:00:00',
        'title'                  => 'Two Times',
        'recurrence'             => [
          'until'                => '2020-02-05 17:00:00',
          'frequency'            => 'Daily',
        ],
        'recurrence_description' => 'Twice.',
      ],
    ], $events);
  }

  public function test_recurrences_with_exceptions() {
    // multiple events:
    // - 2/3 - 2/7 10am - 3pm
    //   3 recurrences total, daily, two exceptions on 4th and 6th
    // - 2/4 - 2/5 2pm - 7pm
    //   2 recurrences, daily, two exceptions on 5th and 6th
    $events = [
      [
        'start'                  => '2020-02-03 10:00:00',
        'end'                    => '2020-02-03 15:00:00',
        'title'                  => 'Three Times',
        'recurrence'             => [
          'until'                => '2020-02-07 15:00:00',
          'frequency'            => 'Daily',
          'exceptions'           => ['2020-02-04 10:00:00', '2020-02-06 10:00:00'],
        ],
        'recurrence_description' => 'Thrice.',
      ],
      [
        'start'                  => '2020-02-04 14:00:00',
        'end'                    => '2020-02-04 17:00:00',
        'title'                  => 'Two Times',
        'recurrence'             => [
          'until'                => '2020-02-07 17:00:00',
          'frequency'            => 'Daily',
          'exceptions'           => ['2020-02-05 14:00:00', '2020-02-06 14:00:00'],
        ],
        'recurrence_description' => 'Twice.',
      ],
    ];

    $calendar = new Calendar($events);

    $starts = array_map(function(array $recurrence) {
      return $recurrence['start'];
    }, $calendar->recurrences());

    $this->assertEquals([
      '2020-02-03 10:00:00',
      '2020-02-04 14:00:00',
      '2020-02-05 10:00:00',
      '2020-02-07 10:00:00',
      '2020-02-07 14:00:00',
    ], $starts);
  }
}
