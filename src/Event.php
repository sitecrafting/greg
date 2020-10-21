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
   * The greg_event Post that this object wraps
   *
   * @var Post
   */
  protected $post;

  /**
   * Create a new Event wrapper object to wrap an event Post
   *
   * @param \Timber\Post $post the greg_event Post object to wrap
   */
  public function __construct(Post $post) {
    $this->post = $post;
  }

  /**
   * Convert a Post into an array that Calendar::__construct will accept
   *
   * @internal
   * @param Post $event the greg_event post to convert
   * @return array
   */
  public static function post_to_calendar_series(Post $event) : array {
    // TODO use meta_keys filter
    $recurrence_rules = [];
    $until            = $event->meta('until');
    $frequency        = $event->meta('frequency');

    if ($until && $frequency) {
      $recurrence_rules = [
        'until'      => $until,
        'frequency'  => $frequency,
        'exceptions' => $event->meta('exceptions') ?: [],
      ];
    }

    return [
      'start'                  => $event->meta('start'),
      'end'                    => $event->meta('end'),
      'title'                  => $event->title(),
      'recurrence'             => $recurrence_rules,
      'recurrence_description' => $event->meta('recurrence_description'),
      'post'                   => $event,
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
    return new self($event['post']);
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
}
