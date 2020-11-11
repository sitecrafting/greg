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

  /**
   * Test sorting issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_recurrences_mixed() {
    // multiple events, with the first one recurring
    $events = [
      [
        'start'                  => '2020-10-28 12:00:00',
        'end'                    => '2020-10-28 12:30:00',
        'title'                  => 'Recurring Event',
        'recurrence'             => [
          'until'                => '2020-11-02 12:30:00',
          'frequency'            => 'daily',
          'exceptions'           => [],
        ],
        'recurrence_description' => 'Daily from the 28th thru Nov. 2nd',
      ],
      [
        'start'                  => '2020-10-29 11:00:00',
        'end'                    => '2020-10-29 12:00:00',
        'title'                  => 'Party Planning',
        'recurrence'             => [],
        'recurrence_description' => '',
      ],
      [
        'start'                  => '2020-10-31 21:00:00',
        'end'                    => '2020-10-31 23:30:00',
        'title'                  => 'Costume Party!',
        'recurrence'             => [],
        'recurrence_description' => '',
      ],
    ];

    $calendar = new Calendar($events);

    $summary = array_map(function(array $recurrence) {
      return $recurrence['title'] . ' ' . $recurrence['start'];
    }, $calendar->recurrences());

    $this->assertEquals([
      'Recurring Event 2020-10-28 12:00:00',
      'Party Planning 2020-10-29 11:00:00',
      'Recurring Event 2020-10-29 12:00:00',
      'Recurring Event 2020-10-30 12:00:00',
      'Recurring Event 2020-10-31 12:00:00',
      'Costume Party! 2020-10-31 21:00:00',
      'Recurring Event 2020-11-01 12:00:00',
      'Recurring Event 2020-11-02 12:00:00',
    ], $summary);
  }

  public function test_recurrences_weekly_spanning_multiple_months() {
    $events = [
      [
        'start'                  => '2020-11-11 09:00:00',
        'end'                    => '2020-11-11 17:00:00',
        'title'                  => 'Weekly Event spanning months',
        'recurrence'             => [
          'until'                => '2021-03-10 09:00:00',
          'frequency'            => 'weekly',
          'exceptions'           => [],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $dec_recurrences = $calendar->recurrences([
      'earliest' => '2020-12-01 00:00:00',
      'latest'   => '2020-12-31 23:59:59',
    ]);

    $this->assertEquals('2020-12-02 09:00:00', $dec_recurrences[0]['start']);
    $this->assertEquals('2020-12-09 09:00:00', $dec_recurrences[1]['start']);
    $this->assertEquals('2020-12-16 09:00:00', $dec_recurrences[2]['start']);
    $this->assertEquals('2020-12-23 09:00:00', $dec_recurrences[3]['start']);
    $this->assertEquals('2020-12-30 09:00:00', $dec_recurrences[4]['start']);

    $jan_recurrences = $calendar->recurrences([
      'earliest' => '2021-01-01 00:00:00',
      'latest'   => '2021-01-31 23:59:59',
    ]);

    $this->assertEquals('2021-01-06 09:00:00', $jan_recurrences[0]['start']);
    $this->assertEquals('2021-01-13 09:00:00', $jan_recurrences[1]['start']);
    $this->assertEquals('2021-01-20 09:00:00', $jan_recurrences[2]['start']);
    $this->assertEquals('2021-01-27 09:00:00', $jan_recurrences[3]['start']);

    $feb_recurrences = $calendar->recurrences([
      'earliest' => '2021-02-01 00:00:00',
      'latest'   => '2021-02-28 23:59:59',
    ]);

    $this->assertEquals('2021-02-03 09:00:00', $feb_recurrences[0]['start']);
    $this->assertEquals('2021-02-10 09:00:00', $feb_recurrences[1]['start']);
    $this->assertEquals('2021-02-17 09:00:00', $feb_recurrences[2]['start']);
    $this->assertEquals('2021-02-24 09:00:00', $feb_recurrences[3]['start']);

    $mar_recurrences = $calendar->recurrences([
      'earliest' => '2021-03-01 00:00:00',
      'latest'   => '2021-03-31 23:59:59',
    ]);

    $this->assertEquals('2021-03-03 09:00:00', $mar_recurrences[0]['start']);
  }

  /**
   * Test date-limit issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_recurrences_limit_earliest() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:30:00',
        'title'                  => 'Recurring Event',
        'recurrence'             => [
          'until'                => '2020-11-15 12:00:00',
          'frequency'            => 'daily',
          'exceptions'           => [],
        ],
        'recurrence_description' => 'Daily for a hecka long time',
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences([
      'earliest' => '2020-10-01',
    ]);

    $this->assertCount(31 + 15, $recurrences);
    $this->assertEquals('2020-10-01 12:00:00', $recurrences[0]['start']);
    $this->assertEquals('2020-11-15 12:00:00', $recurrences[45]['start']);
  }

  /**
   * Test date-limit issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_recurrences_limit_latest() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:30:00',
        'title'                  => 'Recurring Event',
        'recurrence'             => [
          'until'                => '2020-11-15 12:00:00',
          'frequency'            => 'daily',
          'exceptions'           => [],
        ],
        'recurrence_description' => 'Daily for a hecka long time',
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences([
      'latest' => '2020-10-31 23:59:59',
    ]);

    $this->assertCount(6 + 31, $recurrences);
    $this->assertEquals('2020-09-25 12:00:00', $recurrences[0]['start']);
    $this->assertEquals('2020-10-31 12:00:00', $recurrences[36]['start']);
  }

  /**
   * Test exceptions ACF issue: https://github.com/sitecrafting/greg/issues/7
   */
  public function test_recurrences_nested_exceptions() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:30:00',
        'title'                  => 'Recurring Event',
        'recurrence'             => [
          'until'                => '2020-11-15 12:00:00',
          'frequency'            => 'daily',
          // This is how ACF returns repeater data
          'exceptions'           => [
            [
              'exception'        => '2020-09-29 12:00:00',
            ],
            [
              'exception'        => '2020-09-30 12:00:00',
            ],
          ],
        ],
        'recurrence_description' => 'Daily for a hecka long time',
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences([
      'latest' => '2020-09-30 23:59:59',
    ]);

    // September events, minus two exceptions
    $this->assertCount(6 - 2, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_secondly() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:00:01',
        'recurrence'             => [
          'until'                => '2020-09-25 12:00:59',
          'frequency'            => 'secondly',
          'exceptions'           => [
            // Calendar should completely ignore start time in parsing out
            // exception times, since secondly is the finest granularity
            // and doing so would break secondly exceptions.
            '2020-09-25 12:00:05',
            '2020-09-25 12:00:06',
            '2020-09-25 12:00:07',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(60 - 3, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_minutely() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:00:30',
        'recurrence'             => [
          'until'                => '2020-09-25 12:29:00',
          'frequency'            => 'minutely',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time
            // seconds component, based on frequency = MINUTELY.
            '2020-09-25 12:05:01',
            '2020-09-25 12:06:02',
            '2020-09-25 12:07:03',
            '2020-09-25 12:08:04',
            '2020-09-25 12:09:05',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(30 - 5, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_hourly() {
    $events = [
      [
        // Recur over ten hours, with four exceptions.
        'start'                  => '2020-09-25 10:00:00',
        'end'                    => '2020-09-25 10:30:00',
        'recurrence'             => [
          'until'                => '2020-09-25 19:00:00',
          'frequency'            => 'hourly',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time,
            // based on frequency = DAILY.
            '2020-09-25 12:00:00',
            '2020-09-25 13:33:00',
            '2020-09-25 14:12:34',
            '2020-09-25 15:00:33',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(10 - 4, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_daily() {
    $events = [
      [
        'start'                  => '2020-09-25 12:00:00',
        'end'                    => '2020-09-25 12:30:00',
        'recurrence'             => [
          'until'                => '2020-11-15 12:00:00',
          'frequency'            => 'daily',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time,
            // based on frequency = DAILY.
            '2020-09-26',
            '2020-09-27 00:00:00',
            '2020-09-28 00:00:00-08:00',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences([
      'latest' => '2020-09-30 23:59:59',
    ]);

    $this->assertCount(6 - 3, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_weekly() {
    $events = [
      [
        // Six weekly recurrences with two exceptions.
        'start'                  => '2020-09-01 12:00:00',
        'end'                    => '2020-09-01 12:30:00',
        'recurrence'             => [
          'until'                => '2020-10-06 12:00:00',
          'frequency'            => 'weekly',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time,
            // based on frequency = WEEKLY.
            '2020-09-15',
            '2020-09-22 00:00:00',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(6 - 2, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_monthly() {
    $events = [
      [
        // Twelve monthly occurrences, with three exceptions.
        'start'                  => '2020-01-05 12:00:00',
        'end'                    => '2020-01-05 12:30:00',
        'recurrence'             => [
          'until'                => '2020-12-05 12:00:00',
          'frequency'            => 'monthly',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time,
            // based on frequency = MONTHLY.
            '2020-04-20', // should align irrespective of day
            '2020-07-05 23:59:59',
            '2020-09-05 23:59:59',
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(12 - 3, $recurrences);
  }

  /**
   * Test exception format issue: https://github.com/sitecrafting/greg/issues/8
   */
  public function test_recurrences_exception_time_yearly() {
    $events = [
      [
        // Ten annual recurrences, with two exceptions.
        'start'                  => '2018-09-25 12:00:00',
        'end'                    => '2018-09-25 12:30:00',
        'recurrence'             => [
          'until'                => '2027-09-25 12:00:00',
          'frequency'            => 'yearly',
          'exceptions'           => [
            // Calendar should normalize all of these to match start time,
            // based on frequency = YEARLY.
            '2020-09', // I just can't in 2020.
            '2021-09-01', // Next year is borked as well.
          ],
        ],
      ],
    ];

    $calendar = new Calendar($events);

    $recurrences = $calendar->recurrences();

    $this->assertCount(10 -2, $recurrences);
  }
}
