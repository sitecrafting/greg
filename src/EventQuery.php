<?php

/**
 * Greg\EventQuery class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Timber\PostCollection;
use Timber\Timber;

/**
 * Internal class for managing Event query logic
 *
 * @internal
 */
class EventQuery {
  /**
   * The params passed to the constructor
   *
   * @var array
   */
  private $params;

  /**
   * The current time passed to the constructor, as a DateTimeImmutable object
   *
   * @var DateTimeImmutable
   */
  private $time;

  /**
   * The start_time inferred from $params, as a DateTimeImmutable object
   *
   * @var DateTimeImmutable
   */
  private $start_date;

  /**
   * The end_time inferred from $params, as a DateTimeImmutable object
   *
   * @var DateTimeImmutable|false
   */
  private $end_date;

  /**
   * The meta_keys to query by in wp_postmeta
   *
   * @var array
   */
  private $meta_keys;

  /**
   * Create a new EventQuery object with the given high-level params
   *
   * @param array $params params as passed to Greg\get_events()
   * @throws \InvalidArgumentException if current_time, end_date, start_date,
   * or event_month are invalid date strings
   */
  public function __construct(array $params) {
    $current_time = date_create_immutable($params['current_time']);
    if (!$current_time) {
      throw new InvalidArgumentException('Invalid date string: ' . $params['current_time']);
    }
    $this->time = $current_time;

    $this->start_date = $this->init_start_date($params);
    $this->end_date   = $this->init_end_date($params, $this->start_date);
    $this->params     = $params;

    // Use fallback meta_key values.
    $this->meta_keys = [
      'start'      => $params['meta_keys']['start'] ?? 'start',
      'end'        => $params['meta_keys']['end'] ?? 'end',
      'until'      => $params['meta_keys']['until'] ?? 'until',
      'frequency'  => $params['meta_keys']['frequency'] ?? 'frequency',
      'exceptions' => $params['meta_keys']['exceptions'] ?? 'exceptions',
    ];
  }

  /**
   * Get the initial value for $start_date
   *
   * @internal
   * @throws \InvalidArgumentException on unparseable date string
   */
  protected function init_start_date(array $params) : DateTimeImmutable {
    $str = $params['event_month']
      ?? $params['start_date']
      ?? $params['current_time'];

    $date = date_create_immutable($str);

    if (!$date) {
      throw new InvalidArgumentException('Invalid date string: ' . $str);
    }

    return $date;
  }

  /**
   * Get the initial value for $end_date
   *
   * @internal
   * @throws \InvalidArgumentException on unparseable date string
   */
  protected function init_end_date(array $params, DateTimeImmutable $start) : DateTimeImmutable {
    if (!empty($params['end_date'])) {
      $str = $params['end_date'];
    } else {
      // calculate the first day of "next month" relative to $start
      $one_month_from_today = $start->modify('next month');
      $first_of_next_month  = date_create_immutable($one_month_from_today->format('Y-m-01'));
      if (!$first_of_next_month) {
        throw new InvalidArgumentException('Error computing end_date');
      }
      $end_of_this_month = $first_of_next_month->modify('-1 day');
      $str               = $end_of_this_month->format('Y-m-d 23:59:59');
    }

    $date = date_create_immutable($str);

    if (!$date) {
      throw new InvalidArgumentException('Invalid date string: ' . $str);
    }

    return $date;
  }

  /**
   * Get the results for this EventQuery as a collection of zero or more Events
   *
   * @internal
   */
  public function get_results() {
    return Timber::get_posts($this->params());
  }

  /**
   * Get the final params to be passed to WP_Query::__construct()
   *
   * @internal
   */
  public function params() : array {
    return array_merge([
      'post_type'  => 'greg_event',
    ], $this->meta_clause(), $this->tax_clause());
  }

  /**
   * Get the meta_query array to merge into main params, if any
   *
   * @internal
   */
  protected function tax_clause() : array {
    if (empty($this->params['event_category'])) {
      return [];
    }

    // Ensure we have an array
    $cat_filter = $this->params['event_category'];
    $cat_filter = is_array($cat_filter) ? $cat_filter : [$cat_filter];

    // Determine whether we're dealing with term_ids or slugs
    $term_id_count = count(array_filter(array_map('is_int', $cat_filter)));
    $all_ids       = count($cat_filter) === $term_id_count;
    $field         = $all_ids ? 'term_id' : 'slug';

    return [
      'tax_query'    => [
        [
          'taxonomy' => 'greg_event_category',
          'terms'    => $cat_filter,
          'field'    => $field,
        ],
      ],
    ];
  }

  /**
   * Get the meta_query array to merge into main params, if any
   *
   * @internal
   */
  protected function meta_clause() : array {
    // TODO do we still need this check?
    if (empty($this->start_date()) && empty($this->end_date())) {
      return [];
    }

    $start_filter = $this->start_date();
    $end_filter   = $this->end_date();

    $date_query = [
      'relation' => 'OR',
    ];

    if ($start_filter) {
      // event.start BETWEEN start/end filters
      $date_query[] = [
        'key'     => $this->meta_keys['start'],
        'value'   => [$start_filter, $end_filter],
        'compare' => 'BETWEEN',
        'type'    => 'DATETIME',
      ];

      // start filter BETWEEN event.start AND event.end
      $date_query[] = [
        'relation' => 'AND',
        [
          // event starts BEFORE start_filter
          'key'     => $this->meta_keys['start'],
          'value'   => $start_filter,
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          // event ends AFTER start_filter
          'key'     => [$this->meta_keys['end'], $this->meta_keys['until']],
          'value'   => $start_filter,
          'compare' => '>',
          'type'    => 'DATETIME',
        ],
      ];
    }

    if ($end_filter) {
      // event.end/event.until BETWEEN start/end filters
      $date_query[] = [
        'key'     => [$this->meta_keys['end'], $this->meta_keys['until']],
        'value'   => [$start_filter, $end_filter],
        'compare' => 'BETWEEN',
        'type'    => 'DATETIME',
      ];
    }

    return [
      'meta_query' => [$date_query],
    ];
  }

  /**
   * Constraints to pass to Calendar::recurrences()
   *
   * @internal
   */
  public function recurrence_constraints() : array {
    return [
      'earliest'    => $this->start_date(),
      'latest'      => $this->end_date(),
      'event_month' => $this->params['event_month'] ?? '',
    ];
  }

  /**
   * Get the start_date value to query for
   *
   * @internal
   */
  public function start_date() : string {
    if (!empty($this->params['start_date'])) {
      // User passed explicit start_date; honor precisely.
      return $this->start_date->format('Y-m-d 00:00:00');
    }

    if ($this->truncate() && $this->within_current_month($this->start_date)) {
      return $this->time->format('Y-m-d 00:00:00');
    }

    return $this->start_date->format('Y-m-01 00:00:00');
  }

  /**
   * Get the end_date value to query for
   *
   * @internal
   */
  public function end_date() : string {
    if ($this->end_date) {
      return $this->end_date->format('Y-m-d 23:59:59');
    }

    return '';
  }

  /**
   * Whether the given date is within the current month according to the
   * current_time passed to the constructor.
   *
   * @internal
   */
  protected function within_current_month(DateTimeImmutable $t) : bool {
    return $t->format('ym') === $this->time->format('ym');
  }

  /**
   * Whether to truncate events that fall within the current month, based on
   * the caller's passed $params
   *
   * @internal
   */
  protected function truncate() : bool {
    return (bool) ($this->params['truncate_current_month'] ?? false);
  }
}
