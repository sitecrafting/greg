<?php

/**
 * Greg\EventQuery class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use \DateTimeImmutable;

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
   * The start_time passed to the constructor, as a DateTimeImmutable object
   *
   * @var DateTimeImmutable|false
   */
  private $start_date;

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
    // TODO end_date
    if (empty($this->start_date())) {
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
