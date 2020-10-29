<?php

/**
 * Greg\Integration\GregTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Integration;

use InvalidArgumentException;
use Greg;
use Greg\Event;

/**
 * Test case for the core query logic within the public Greg API.
 *
 * @group integration
 */
class GregTest extends IntegrationTest {
  public function test_get_events_no_results() {
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Single Event',
      // Events exist, but for whatever reason don't have valid start/end date info
    ]);

    $this->assertEmpty(Greg\get_events());
  }

  public function test_get_events_no_current_events() {
    // Events exist, but not within the current month
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Old Event',
      'meta_input' => [
        'start'    => date_create_immutable('now')->modify('-6 weeks')->format('Y-m-d 00:00:00'),
        'end'      => date_create_immutable('now')->modify('-5 weeks')->format('Y-m-d 00:00:00'),
      ],
    ]);
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Upcoming Event',
      'meta_input' => [
        'start'    => date_create_immutable('now')->modify('+5 weeks')->format('Y-m-d 00:00:00'),
        'end'      => date_create_immutable('now')->modify('+6 weeks')->format('Y-m-d 00:00:00'),
      ],
    ]);

    $this->assertEmpty(Greg\get_events());
  }

  public function test_get_events_single_default() {
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Single Event',
      'meta_input' => [
        'start'    => '2020-03-03 09:00:00',
        'end'      => '2020-03-04 09:00:00',
      ],
    ]);

    $events = Greg\get_events([
      'current_time' => '2020-03-01',
    ]);

    $this->assertCount(1, $events);
    $this->assertInstanceOf(Event::class, $events[0]);
    $this->assertEquals('My Single Event', $events[0]->title());
  }

  public function test_get_events_with_recurrences() {
    $this->factory->post->create([
      'post_type'   => 'greg_event',
      'post_title'  => 'My Recurring Event',
      'meta_input'  => [
        'start'     => '2020-10-11 10:00:00',
        'end'       => '2020-10-12 11:00:00',
        'frequency' => 'DAILY',
        'until'     => '2020-10-17 10:00:00',
      ],
    ]);

    $events = Greg\get_events([
      'current_time' => '2020-10-10',
    ]);

    $this->assertCount(7, $events);
    foreach ($events as $event) {
      $this->assertInstanceOf(Event::class, $event);
      $this->assertEquals('My Recurring Event', $event->title());
    }
  }

  public function test_get_events_with_recurrences_and_exceptions() {
    $this->factory->post->create([
      'post_type'    => 'greg_event',
      'post_title'   => 'My Recurring Event',
      'meta_input'   => [
        'start'      => '2020-10-11 10:00:00',
        'end'        => '2020-10-12 11:00:00',
        'frequency'  => 'DAILY',
        'until'      => '2020-10-17 10:00:00',
        'exceptions' => ['2020-10-13 10:00:00', '2020-10-16 10:00:00'],
      ],
    ]);

    $events = Greg\get_events([
      'current_time' => '2020-10-02',
    ]);

    $this->assertCount(5, $events);
    foreach ($events as $event) {
      $this->assertInstanceOf(Event::class, $event);
      $this->assertEquals('My Recurring Event', $event->title());
    }
  }

  public function test_get_events_skip_expansion() {
    $this->factory->post->create([
      'post_type'   => 'greg_event',
      'post_title'  => 'My Event Series',
      'meta_input'  => [
        'start'     => '2020-09-05 14:00:00',
        'end'       => '2020-09-05 16:30:00',
        'frequency' => 'DAILY',
        'until'     => '2020-09-11 14:00:00',
      ],
    ]);

    $events = Greg\get_events([
      'expand_recurrences' => false,
      'current_time'       => '2020-09',
    ]);

    $this->assertCount(1, $events);
    $this->assertInstanceOf(Event::class, $events[0]);
    $this->assertEquals('My Event Series', $events[0]->title());
  }

  public function test_get_events_mixed() {
    $event_data = [
      [
        'post_type'                => 'greg_event',
        'post_title'               => 'Costume Party!',
        'post_date'                => '2020-10-21 00:00:00',
        'meta_input'               => [
          'start'                  => '2020-10-31 21:00:00',
          'end'                    => '2020-10-31 23:30:00',
          'recurrence_description' => '',
        ],
      ],
      [
        'post_type'                => 'greg_event',
        'post_title'               => 'Party Planning',
        'post_date'                => '2020-10-22 00:00:00',
        'meta_input'               => [
          'start'                  => '2020-10-29 11:00:00',
          'end'                    => '2020-10-29 12:00:00',
          'recurrence_description' => '',
        ],
      ],
      [
        'post_type'                => 'greg_event',
        'post_title'               => 'Recurring Event',
        'post_date'                => '2020-10-23 00:00:00',
        'meta_input'               => [
          'start'                  => '2020-10-28 12:00:00',
          'end'                    => '2020-10-28 12:30:00',
          'until'                  => '2020-11-02 12:00:00',
          'frequency'              => 'daily',
          'exceptions'             => [],
          'recurrence_description' => 'Daily from the 28th thru Nov. 2nd',
        ],
      ],
    ];

    foreach ($event_data as $data) {
      $this->factory->post->create($data);
    }

    $events = Greg\get_events([
      'event_month'  => '2020-10',
      'current_time' => '2020-10-25 00:00:00',
    ]);

    $summary = array_map(function(Event $recurrence) : string {
      return $recurrence->title() . ' ' . $recurrence->start('m/j g:i');
    }, $events);

    $this->assertEquals([
      'Recurring Event 10/28 12:00',
      'Party Planning 10/29 11:00',
      'Recurring Event 10/29 12:00',
      'Recurring Event 10/30 12:00',
      'Recurring Event 10/31 12:00',
      'Costume Party! 10/31 9:00',
    ], $summary);
  }

  /**
   * Test date-limit issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_get_events_limit_earliest() {
    // multiple events, with the first one recurring
    $this->factory->post->create([
      'post_title'   => 'Recurring Event',
      'post_type'    => 'greg_event',
      'meta_input'   => [
        'start'      => '2020-09-25 12:00:00',
        'end'        => '2020-09-25 12:30:00',
        'until'      => '2020-11-15 12:00:00',
        'frequency'  => 'daily',
        'exceptions' => [],
      ],
    ]);

    $recurrences = Greg\get_events([
      'start_date'   => '2020-10-01',
      'end_date'     => '2020-12-31',
      'current_time' => '2020-10-01',
    ]);

    // 31 in Oct, 15 in Nov
    $this->assertCount(31 + 15, $recurrences);
    $this->assertEquals('2020-10-01', $recurrences[0]->start('Y-m-d'));
    $this->assertEquals('2020-11-15', $recurrences[45]->start('Y-m-d'));
  }

  /**
   * Test date-limit issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_get_events_limit_latest() {
    $this->factory->post->create([
      'post_title'   => 'Recurring Event',
      'post_type'    => 'greg_event',
      'meta_input'   => [
        'start'      => '2020-09-25 12:00:00',
        'end'        => '2020-09-25 12:30:00',
        'until'      => '2020-11-15 12:00:00',
        'frequency'  => 'daily',
        'exceptions' => [],
      ],
    ]);

    $recurrences = Greg\get_events([
      'current_time' => '2020-10-01',
      // start_date defaults to current_time
      'end_date'     => '2020-10-31',
    ]);

    $this->assertCount(31, $recurrences);
    $this->assertEquals('2020-10-01', $recurrences[0]->start('Y-m-d'));
    $this->assertEquals('2020-10-31', $recurrences[30]->start('Y-m-d'));
  }

  /**
   * Test date-limit issue: https://github.com/sitecrafting/greg/issues/4
   */
  public function test_get_events_limit_event_month() {
    $this->factory->post->create([
      'post_title'   => 'Recurring Event',
      'post_type'    => 'greg_event',
      'meta_input'   => [
        'start'      => '2020-09-25 12:00:00',
        'end'        => '2020-09-25 12:30:00',
        'until'      => '2020-11-15 12:00:00',
        'frequency'  => 'daily',
        'exceptions' => [],
      ],
    ]);

    $recurrences = Greg\get_events([
      'event_month'  => '2020-10',
      'current_time' => '2020-10-01',
    ]);

    $this->assertCount(31, $recurrences);
    $this->assertEquals('2020-10-01', $recurrences[0]->start('Y-m-d'));
    $this->assertEquals('2020-10-31', $recurrences[30]->start('Y-m-d'));
  }

  public function test_get_events_by_category() {
    $term_id = $this->factory->term->create([
      'taxonomy' => 'greg_event_category',
      'name'     => 'Dogs',
      'slug'     => 'dogs',
    ]);

    $id = $this->factory->post->create([
      'post_title'   => 'COME PLAY WITH DOGS',
      'post_type'    => 'greg_event',
      'meta_input'   => [
        'start'      => '2020-09-25 12:00:00',
        'end'        => '2020-09-25 12:30:00',
        'until'      => '2020-11-15 12:00:00',
        'frequency'  => 'daily',
        'exceptions' => [],
      ],
    ]);
    wp_set_post_terms($id, [$term_id], 'greg_event_category');

    $this->factory->post->create_many(3, [
      'post_type'    => 'greg_event',
      'meta_input'   => [
        'start'      => '2020-09-25 12:00:00',
        'end'        => '2020-09-25 12:30:00',
        'until'      => '2020-11-15 12:00:00',
        'frequency'  => 'daily',
        'exceptions' => [],
      ],
    ]);

    $events = Greg\get_events([
      'event_month'        => '2020-10',
      'event_category'     => 'dogs',
      'expand_recurrences' => false,
    ]);

    $this->assertCount(1, $events);
    $this->assertEquals('COME PLAY WITH DOGS', $events[0]->title());
    $this->assertEquals('Dogs', $events[0]->terms('greg_event_category')[0]->title());
  }

  public function test_params_filter_default_month() {
    set_query_var('event_month', '2020-10');

    $this->assertEquals('2020-10', apply_filters('greg/params', [])['event_month']);
  }

  public function test_event_month_default() {
    // @phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
    $this->assertEquals(date('Y-m'), Greg\event_month());
  }

  public function test_event_month() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('2020-09', Greg\event_month());
  }

  public function test_event_month_format() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('September 2020', Greg\event_month('F Y'));
  }

  public function test_event_month_garbage() {
    set_query_var('event_month', 'COMPLETE GARBAGE');
    $this->assertEquals('', Greg\event_month());
  }

  public function test_prev_month() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('2020-08', Greg\prev_month());
  }

  public function test_prev_month_format() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('August 2020', Greg\prev_month('F Y'));
  }

  public function test_prev_month_garbage() {
    set_query_var('event_month', 'NO NO NO NO NO NO');
    $this->assertEquals('', Greg\prev_month());
  }

  public function test_next_month() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('2020-10', Greg\next_month());
  }

  public function test_next_month_format() {
    set_query_var('event_month', '2020-09');
    $this->assertEquals('October 2020', Greg\next_month('F Y'));
  }

  public function test_next_month_garbage() {
    set_query_var('event_month', 'NOT THIS GARBAGE AGAIN');
    $this->assertEquals('', Greg\next_month());
  }
}
