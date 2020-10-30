<?php

/**
 * Greg\Event class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use DateTimeImmutable;
use InvalidArgumentException;
use Timber\CoreInterface;
use Timber\Post;

/**
 * Wrapper class for a single recurrence of an Event post.
 */
class Event implements CoreInterface {
  /**
   * Default meta_keys for all querying/lookup purposes
   *
   * @api
   * @var array
   */
  const DEFAULT_META_KEYS = [
    'start'                  => 'start',
    'end'                    => 'end',
    'until'                  => 'until',
    'frequency'              => 'frequency',
    'exceptions'             => 'exceptions',
    'recurrence_description' => 'recurrence_description',
  ];

  /**
   * The greg_event Post that this object wraps
   *
   * @var Post
   */
  protected $post;

  /**
   * Options for specific fields
   *
   * @var array
   */
  protected $options;

  /**
   * Internal start datetime
   *
   * @var DateTimeImmutable
   */
  protected $start_datetime;

  /**
   * Internal end datetime
   *
   * @var DateTimeImmutable
   */
  protected $end_datetime;

  /**
   * Internal until datetime
   *
   * @var DateTimeImmutable|false
   */
  protected $until_datetime;

  /**
   * Create a new Event wrapper object to wrap an event Post
   *
   * @param \Timber\Post $post the greg_event Post object to wrap
   * @param array $options optional options for specific fields. The
   * following fields are supported:
   * * start - for recurrences, which have their own independent start dates
   * * end - for recurrences, which also have their own independent end dates
   * * recurrence_description
   * @throws InvalidArgumentException if start or end is not a parseable
   * date string.
   */
  public function __construct(Post $post, array $options = []) {
    $this->post    = $post;
    $this->options = $options;

    if (!empty($options['start'])) {
      $start = date_create_immutable($options['start']);
      if (!$start) {
        throw new InvalidArgumentException('Invalid date string: ' . $options['start']);
      }
      $this->start_datetime = $start;
    }

    if (!empty($options['end'])) {
      $end = date_create_immutable($options['end']);
      if (!$end) {
        throw new InvalidArgumentException('Invalid date string: ' . $options['end']);
      }
      $this->end_datetime = $end;
    }
  }

  /**
   * Convert a Post into an array that Calendar::__construct will accept
   *
   * @internal
   * @param Post $post the greg_event post to convert
   * @return array
   */
  public static function post_to_calendar_series(Post $post) : array {
    // TODO use meta_keys filter
    $recurrence_rules = [];
    $until            = $post->meta(meta_key('until'));
    $frequency        = $post->meta(meta_key('frequency'));

    if ($until && $frequency) {
      $recurrence_rules = [
        'until'      => $until,
        'frequency'  => $frequency,
        'exceptions' => $post->meta(meta_key('exceptions')) ?: [],
      ];
    }

    return [
      'start'                  => $post->meta(meta_key('start')),
      'end'                    => $post->meta(meta_key('end')),
      'title'                  => $post->title(),
      'recurrence'             => $recurrence_rules,
      'recurrence_description' => $post->meta(meta_key('recurrence_description')),
      'post'                   => $post,
    ];
  }

  /**
   * Create a new Event wrapper object from a Post. Intended for singular,
   * non-recurring Events. For recurring Events, always ensure you pass
   * start/end options specific to each recurrence.
   *
   * @api
   * @param \Timber\Post $post a Post object, presumably of type `greg_event`.
   * @return self
   */
  public static function from_post(Post $post) : self {
    return new self($post);
  }

  /**
   * Create a new Event wrapper object from an internal array representation
   * of a recurring event, which MUST include a `post` index.
   * Always ensure you pass start/end options specific to each recurrence.
   *
   * @internal
   */
  public static function from_assoc(array $event) {
    $options = array_intersect_key($event, array_flip([
      'recurrence_description',
      'start',
      'end',
    ]));
    return new self($event['post'], $options);
  }

  /**
   * Magic method to delegate non-existent methods to $post
   *
   * @internal
   * @param string $method the method name
   * @param array $args the method args
   */
  public function __call($method, $args) {
    $func = [$this->post, $method];
    if (!is_callable($func)) {
      return false;
    }
    return $func(...$args);
  }

  /**
   * Magic method to get arbitrary fields, delegating to $post
   *
   * @internal
   * @param string $field the field name
   */
  public function __get($field) {
    return $this->post->$field;
  }

  /**
   * Magic method to determine whether an arbitrary field is set, delegating
   * to $post
   *
   * @internal
   * @param string $field the field name
   */
  public function __isset($field) {
    return isset($this->post->$field);
  }



  /* API METHODS */


  /**
   * Start/End date/time range
   *
   * @param string $start_format the format for displaying start date/time
   * @param string $end_format the format for displaying end date/time
   * @param string $separator the string to place between start and end
   * @return string
   */
  public function range(
    string $start_format = '',
    string $end_format = '',
    string $separator = ' - '
  ) : string {
    return $this->start($start_format) . $separator . $this->end($end_format);
  }

  /**
   * Start date/time, optionally formatted
   *
   * @param string $format optional date string format string, defaults to global
   * WordPress date_format and time_format options, separated by a space
   * @return string
   */
  public function start(string $format = '') : string {
    // TODO improve flexibility of this with filters
    $format = $format
      ?: get_option('date_format') . ' ' . get_option('time_format');
    return $this->start_datetime()->format($format);
  }

  /**
   * End date/time, optionally formatted
   *
   * @param string $format optional date string format string, defaults to global
   * WordPress time_format option
   * @return string
   */
  public function end(string $format = '') : string {
    // TODO improve flexibility of this with filters
    $format = $format
      ?: get_option('time_format');
    return $this->end_datetime()->format($format);
  }

  /**
   * Until date/time, optionally formatted
   *
   * @param string $format optional date string format string, defaults to global
   * WordPress date_format option
   * @return string
   */
  public function until(string $format = '') : string {
    // TODO improve flexibility of this with filters
    $format = $format
      ?: get_option('date_format');

    if (empty($this->until_datetime())) {
      return '';
    }

    return $this->until_datetime()->format($format);
  }

  /**
   * Whether this Event recurs or not
   *
   * @return bool
   */
  public function recurring() : bool {
    return $this->post->meta(meta_key('until'))
      && $this->post->meta(meta_key('frequency'));
  }

  /**
   * Human-readable description of this Event's recurrence rules
   *
   * @return string
   */
  public function recurrence_description() : string {
    return $this->post->meta(meta_key('recurrence_description'))
      ?: $this->options['recurrence_description']
      ?: ''; // ensure we have a string
  }

  /**
   * Human-readable frequency
   *
   * @return string
   */
  public function frequency() : string {
    $freq = $this->post->meta(meta_key('frequency')) ?: '';
    return $freq ? ucfirst(strtolower($freq)) : '';
  }



  /* HELPER METHODS */


  /**
   * Get the start date/time string from $post and create a DateTimeImmutable
   * object from it
   *
   * @internal
   * @throws InvalidArgumentException if the stored string is unparseable
   */
  protected function start_datetime() : DateTimeImmutable {
    if (!isset($this->start_datetime)) {
      $str = $this->post->meta(meta_key('start'));
      $dt  = date_create_immutable($str);
      if (!$dt) {
        // This won't happen unless you're doing something really weird,
        // since we already queried this event by `start` which means we
        // effectively parsed this date already.
        throw new InvalidArgumentException('Invalid date string: ' . $str);
      }
      $this->start_datetime = $dt;
    }

    return $this->start_datetime;
  }

  /**
   * Get the end date/time string from $post and create a DateTimeImmutable
   * object from it
   *
   * @internal
   * @throws InvalidArgumentException if the stored string is unparseable
   */
  protected function end_datetime() : DateTimeImmutable {
    if (!isset($this->end_datetime)) {
      $str = $this->post->meta(meta_key('end'));
      $dt  = date_create_immutable($str);
      if (!$dt) {
        // This won't happen unless you're doing something really weird,
        // since we already queried this event by `start` which means we
        // effectively parsed this date already.
        throw new InvalidArgumentException('Invalid date string: ' . $str);
      }
      $this->end_datetime = $dt;
    }

    return $this->end_datetime;
  }

  /**
   * Get the until date/time string from $post and create a DateTimeImmutable
   * object from it
   *
   * @internal
   * @return DateTimeImmutable|false
   */
  protected function until_datetime() {
    if (!isset($this->until_datetime)) {
      $str                  = $this->post->meta(meta_key('until'));
      $this->until_datetime = $str
        ? date_create_immutable($str)
        : false;
    }

    return $this->until_datetime;
  }
}
