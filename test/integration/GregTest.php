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
        'end'      => date_create_immutable('now')->modify('+5 weeks')->format('Y-m-d 00:00:00'),
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
        'start'    => date_create_immutable('now')->modify('+24 hours')->format('Y-m-d 00:00:00'),
        'end'      => date_create_immutable('now')->modify('+27 hours')->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events();

    $this->assertCount(1, $events);
    $this->assertInstanceOf(Event::class, $events[0]);
    $this->assertEquals('My Single Event', $events[0]->title());
  }

  public function test_get_events_with_recurrences() {
    $start = date_create_immutable('now')->modify('+24 hours');
    $end   = $start->modify('+25 hours');
    $until = $start->modify('+1 week');

    $this->factory->post->create([
      'post_type'   => 'greg_event',
      'post_title'  => 'My Recurring Event',
      'meta_input'  => [
        'start'     => $start->format('Y-m-d 00:00:00'),
        'end'       => $end->format('Y-m-d 00:00:00'),
        'frequency' => 'DAILY',
        'until'     => $until->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events();

    $this->assertCount(8, $events);
    foreach ($events as $event) {
      $this->assertInstanceOf(Event::class, $event);
      $this->assertEquals('My Recurring Event', $event->title());
    }
  }

  public function test_get_events_with_recurrences_and_exceptions() {
    $start   = date_create_immutable('now')->modify('+24 hours');
    $end     = $start->modify('+25 hours');
    $until   = $start->modify('+1 week');
    $except1 = $start->modify('+48 hours');
    $except2 = $start->modify('+120 hours');

    $this->factory->post->create([
      'post_type'    => 'greg_event',
      'post_title'   => 'My Recurring Event',
      'meta_input'   => [
        'start'      => $start->format('Y-m-d 00:00:00'),
        'end'        => $end->format('Y-m-d 00:00:00'),
        'frequency'  => 'DAILY',
        'until'      => $until->format('Y-m-d 00:00:00'),
        'exceptions' => [$except1->format('Y-m-d 00:00:00'), $except2->format('Y-m-d 00:00:00')],
      ],
    ]);

    $events = Greg\get_events();

    $this->assertCount(6, $events);
    foreach ($events as $event) {
      $this->assertInstanceOf(Event::class, $event);
      $this->assertEquals('My Recurring Event', $event->title());
    }
  }

  public function test_get_events_skip_expansion() {
    $start = date_create_immutable('now')->modify('+24 hours');
    $end   = $start->modify('+25 hours');
    $until = $start->modify('+1 week');

    $this->factory->post->create([
      'post_type'   => 'greg_event',
      'post_title'  => 'My Event Series',
      'meta_input'  => [
        'start'     => $start->format('Y-m-d 00:00:00'),
        'end'       => $end->format('Y-m-d 00:00:00'),
        'frequency' => 'DAILY',
        'until'     => $until->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events([
      'expand_recurrences' => false,
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
      'Recurring Event 11/1 12:00',
      'Recurring Event 11/2 12:00',
    ], $summary);
  }
}
