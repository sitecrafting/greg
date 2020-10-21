<?php

/**
 * Greg\Event class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

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
   * Fallbacks for specific fields
   *
   * @var array
   */
  protected $fallbacks;

  /**
   * Create a new Event wrapper object to wrap an event Post
   *
   * @param \Timber\Post $post the greg_event Post object to wrap
   * @param array $fallbacks optional fallbacks for specific fields. The
   * following fields are supported:
   * * recurrence_description
   */
  public function __construct(Post $post, array $fallbacks = []) {
    $this->post      = $post;
    $this->fallbacks = $fallbacks;
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
   * Create a new Event wrapper object from a Post
   *
   * @internal
   */
  public static function from_post(Post $post) {
    return new self($post);
  }

  /**
   * Create a new Event wrapper object from an internal array representation
   * of a recurring event, which MUST include a `post` index.
   *
   * @internal
   */
  public static function from_assoc(array $event) {
    $fallbacks = array_intersect_key($event, array_flip([
      'recurrence_description',
    ]));
    return new self($event['post'], $fallbacks);
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
   * Whether this Event recurs or not
   *
   * @return bool
   */
  public function recurring() : bool {
    return $this->post->meta('until') && $this->post->meta('frequency');
  }

  /**
   * Human-readable description of this Event's recurrence rules
   *
   * @return string
   */
  public function recurrence_description() : string {
    return $this->post->meta(meta_key('recurrence_description'))
      ?: $this->fallbacks['recurrence_description']
      ?: ''; // ensure we have a string
  }
}
