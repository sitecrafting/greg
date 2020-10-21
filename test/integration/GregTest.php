<?php

/**
 * Greg\Integration\EventQueryTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Integration;

use InvalidArgumentException;
use Greg;

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
        'start_date' => date_create_immutable('now')->modify('-6 weeks')->format('Y-m-d 00:00:00'),
        'end_date'   => date_create_immutable('now')->modify('+5 weeks')->format('Y-m-d 00:00:00'),
      ],
    ]);
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Upcoming Event',
      'meta_input' => [
        'start_date' => date_create_immutable('now')->modify('+5 weeks')->format('Y-m-d 00:00:00'),
        'end_date'   => date_create_immutable('now')->modify('+6 weeks')->format('Y-m-d 00:00:00'),
      ],
    ]);

    $this->assertEmpty(Greg\get_events());
  }

  public function test_get_events_single_default() {
    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Single Event',
      'meta_input' => [
        'start_date' => date_create_immutable('now')->modify('+24 hours')->format('Y-m-d 00:00:00'),
        'end_date'   => date_create_immutable('now')->modify('+27 hours')->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events();

    $this->assertCount(1, $events);
    // TODO
    // $this->assertEquals('My Single Event', $events[0]->title());
    $this->assertEquals('My Single Event', $events[0]['title']);
  }

  public function test_get_events_with_recurrences() {
    $start = date_create_immutable('now')->modify('+24 hours');
    $end   = $start->modify('+25 hours');
    $until = $start->modify('+1 week');

    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Single Event',
      'meta_input' => [
        'start_date' => $start->format('Y-m-d 00:00:00'),
        'end_date'   => $end->format('Y-m-d 00:00:00'),
        'frequency'  => 'DAILY',
        'until'      => $until->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events();

    $this->assertCount(8, $events);
    $this->assertEquals('My Single Event', $events[0]['title']); // TODO call $event->title() instead
  }

  public function test_get_events_skip_expansion() {
    $start = date_create_immutable('now')->modify('+24 hours');
    $end   = $start->modify('+25 hours');
    $until = $start->modify('+1 week');

    $this->factory->post->create([
      'post_type'  => 'greg_event',
      'post_title' => 'My Single Event',
      'meta_input' => [
        'start_date' => $start->format('Y-m-d 00:00:00'),
        'end_date'   => $end->format('Y-m-d 00:00:00'),
        'frequency'  => 'DAILY',
        'until'      => $until->format('Y-m-d 00:00:00'),
      ],
    ]);

    $events = Greg\get_events([
      'expand_recurrences' => false,
    ]);

    $this->assertCount(1, $events);
    $this->assertEquals('My Single Event', $events[0]->title());
  }
}