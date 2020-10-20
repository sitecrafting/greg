<?php

/**
 * Greg\EventQuery class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use \DateTimeImmutable;
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
   * @var DateTimeImmutable|false
   */
  private $time;

  /**
   * The start_time inferred from $params, as a DateTimeImmutable object
   *
   * @var DateTimeImmutable|false
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
   */
  public function __construct(array $params) {
    $this->params     = $params;
    $this->time       = date_create_immutable($params['current_time']);
    $this->start_date = $this->init_start_date($params);
    $this->end_date   = $this->init_end_date($params, $this->start_date);

    $this->meta_keys = [
      'start_date' => $params['meta_keys']['start_date'] ?? 'start_date',
      'end_date'   => $params['meta_keys']['end_date'] ?? 'end_date',
      'until'      => $params['meta_keys']['until'] ?? 'until',
      'frequency'  => $params['meta_keys']['frequency'] ?? 'frequency',
      'exceptions' => $params['meta_keys']['exceptions'] ?? 'exceptions',
    ];
  }

  /**
   * Get the initial value for $start_date
   *
   * @internal
   */
  protected function init_start_date(array $params) : DateTimeImmutable {
    if (!empty($params['event_month'])) {
      $start = date_create_immutable($params['event_month']);
      if ($start) {
        return $start;
      }
    }

    return date_create_immutable($params['start_date'] ?? $params['current_time']);
  }

  /**
   * Get the initial value for $end_date
   *
   * @internal
   */
  protected function init_end_date(array $params, DateTimeImmutable $start) : DateTimeImmutable {
    if (!empty($params['end_date'])) {
      return date_create_immutable($params['end_date']);
    } else {
      $one_month_from_today = $start->modify('next month');
      $first_of_next_month  = date_create_immutable($one_month_from_today->format('Y-m-01'));
      $end_of_this_month    = $first_of_next_month->modify('-1 day');
      return date_create_immutable($end_of_this_month->format('Y-m-d 23:59:59'));
    }
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
    ], $this->meta());
  }

  /**
   * Get the meta_query array to merge into main params, if any
   *
   * @internal
   */
  protected function meta() : array {
    if (empty($this->start_date()) && empty($this->end_date())) {
      return [];
    }

    $meta = [
      'relation' => 'AND',
    ];

    $start = $this->start_date();
    if ($start) {
      $meta[] = [
        'key'     => $this->meta_keys['start_date'],
        'value'   => $start,
        'compare' => '>=',
        'type'    => 'DATETIME',
      ];
    }

    $end = $this->end_date();
    if ($end) {
      $meta[] = [
        'relation' => 'OR',
        [
          'key'     => $this->meta_keys['end_date'],
          'value'   => $end,
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
        [
          'key'     => $this->meta_keys['until'],
          'value'   => $end,
          'compare' => '<=',
          'type'    => 'DATETIME',
        ],
      ];
    }

    return [
      'meta_query' => $meta,
    ];
  }

  /**
   * Get the start_date value to query for
   *
   * @internal
   */
  protected function start_date() : string {
    if (!empty($this->params['start_date']) && $this->start_date) {
      // User passed explicit start_date; honor precisely.
      return $this->start_date->format('Y-m-d 00:00:00');
    }

    if ($this->time && $this->truncate() && $this->within_current_month($this->start_date)) {
      return $this->time->format('Y-m-d 00:00:00');
    }

    if ($this->start_date) {
      return $this->start_date->format('Y-m-01 00:00:00');
    }

    return '';
  }

  /**
   * Get the end_date value to query for
   *
   * @internal
   */
  protected function end_date() : string {
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
