<?php

/**
 * Public API for the Greg plugin
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

use DateTimeImmutable;
use InvalidArgumentException;
use Timber\Timber;

/**
 * Render a Twig template given some data, also passing it through Greg's view
 * data filters. Returns the rendered markup. Analagous to Timber\compile().
 *
 * @example
 * ```php
 * // First let's add some custom data to be passed to the
 * // `event-categories` view:
 * add_filter('greg/twig/render/event-categories', function(array $data) : array {
 *   $data['extra_stuff'] = [
 *     'extra' => 'info',
 *   ];
 *
 *   return $data;
 * });
 *
 * // Now let's render the view:
 * Greg\render('event-categories');
 * ```
 * @param string $view the name of the view
 * @param array $data optional data to pass to the view, as in Timber::render().
 * @return bool|string the rendered markup, or false on failure
 */
function compile(string $view, array $data = []) {
  return Timber::compile(
    '@greg/' . $view,
    apply_filters('greg/render/' . $view, $data)
  );
}

/**
 * Render a Twig template given some data, also passing it through Greg's view
 * data filters. Echoes the rendered markup. Analagous to Timber\render().
 *
 * @example
 * ```php
 * // First let's add some custom data to be passed to the
 * // `event-categories` view:
 * add_filter('greg/twig/render/event-categories', function(array $data) : array {
 *   $data['extra_stuff'] = [
 *     'extra' => 'info',
 *   ];
 *
 *   return $data;
 * });
 *
 * // Now let's render the view:
 * Greg\render('event-categories');
 * ```
 * @param string $view the name of the view
 * @param array $data optional data to pass to the view, as in Timber::render().
 */
function render(string $view, array $data = []) : void {
  echo compile($view, $data);
}

/**
 * Query Events by month, date range, category, or any other valid WP_Query
 * params
 *
 * @example
 * ```php
 * // Default: All events this month
 * $events = Greg\get_events();
 *
 * // Truncate this month's events to ones starting today at the earliest
 * $events = Greg\get_events([
 *   'truncate_current_month' => true,
 * ]);
 *
 * // Skip expanding recurrences; get each event series as a whole
 * $events = Greg\get_events([
 *   'expand_recurrences' => false,
 * ]);
 *
 * // Query by month
 * $events = Greg\get_events([
 *   'event_month' => '2020-03',
 * ]);
 * ```
 * @param array $params event query params
 * @return array|false
 */
function get_events(array $params = []) {
  $params = apply_filters('greg/params', $params);

  try {
    $query = new EventQuery($params);
  } catch (InvalidArgumentException $e) {
    do_action('greg/query/error', $e, [
      'params' => $params,
    ]);
    return false;
  }

  $events = $query->get_results();

  if (!$events) {
    return false;
  }

  // Unless recurrence expansion is explicitly disabled, expand each
  // (potentially) recurring event into its comprising recurrences.
  if ($params['expand_recurrences'] ?? true) {
    $events      = array_map([Event::class, 'post_to_calendar_series'], $events->to_array());
    $calendar    = new Calendar($events);
    $constraints = $query->recurrence_constraints();

    return array_map([Event::class, 'from_assoc'], $calendar->recurrences($constraints));
  } else {
    return array_map([Event::class, 'from_post'], $events->to_array());
  }
}

/**
 * Get the meta key for a given field
 *
 * @param string $field the field to get; one of:
 * * start
 * * end
 * * until
 * * frequency
 * * exceptions
 * * overrides
 * * recurrence_description
 * @return string
 * @throws InvalidArgumentException if passed a bad field name, or if the key
 * does not exist in the configured greg/meta_fields hook.
 */
function meta_key(string $field) : string {
  $key = apply_filters('greg/meta_keys', [])[$field] ?? false;
  if (!$key) {
    throw new InvalidArgumentException(sprintf(
      'Key "%s" not found in the values returned from the greg/meta_keys filter.'
      . ' Did you forget to return this key from your hook?',
      $field
    ));
  }
  return $key;
}

/**
 * Get the currently queried month (according to the event_month query var),
 * formatted as a string. Note that by default the underlying timestamp is the
 * start of the current month at midnight.
 *
 * @param string $format date string format. Default: "Y-m"
 * @return string the formatted event_month string, or the empty string if
 * event_month is not parseable
 */
function event_month(string $format = 'Y-m') : string {
  $month_dt = month_datetime();
  return $month_dt ? $month_dt->format($format) : '';
}

/**
 * Get the previous month based on event_month/current time query var (in that
 * order).
 *
 * @param string $format date string format. Default: "Y-m"
 * @return string the formatted date string, or the empty string if
 * event_month is not parseable
 */
function prev_month(string $format = 'Y-m') : string {
  $month_dt = month_datetime();
  return $month_dt ? $month_dt->modify('-1 month')->format($format) : '';
}

/**
 * Get the next month based on event_month/current time query var (in that
 * order).
 *
 * @param string $format date string format. Default: "Y-m"
 * @return string the formatted date string, or the empty string if
 * event_month is not parseable
 */
function next_month(string $format = 'Y-m') : string {
  $month_dt = month_datetime();
  return $month_dt ? $month_dt->modify('+1 month')->format($format) : '';
}

/**
 * Get the currently queried month (according to the event_month query var),
 * as a DateTimeImmutable object.
 *
 * @return DateTimeImmutable|false a DateTimeImmutable instance of false if
 * event_month is not parseable
 */
function month_datetime() {
  return date_create_immutable(get_query_var('event_month') ?: 'now');
}

/**
 * Get the current Event Category term, if any.
 *
 * @return \Timber\Term|false the current Term according to the global WP_Query
 * or the event_category query var, otherwise false.
 */
function event_category() {
  $ident = get_query_var('event_category') ?: null;

  if (!$ident) {
    // Return the current term, if there is one.
    return Timber::get_term();
  }

  $field   = is_int($ident) ? 'id' : 'slug';
  $wp_term = get_term_by($field, $ident, 'greg_event_category');

  if (!$wp_term) {
    return false;
  }

  return Timber::get_term($wp_term);
}

/**
 * Get the prev_month filter query_string
 *
 * @return string
 */
function prev_month_query_string() {
  $params = [
    'event_month' => prev_month(),
  ];

  $cat = event_category();
  if ($cat) {
    $params['event_category'] = $cat->slug;
  }

  return '?' . http_build_query($params);
}

/**
 * Get the next_month filter query_string
 *
 * @return string
 */
function next_month_query_string() {
  $params = [
    'event_month' => next_month(),
  ];

  $cat = event_category();
  if ($cat) {
    $params['event_category'] = $cat->slug;
  }

  return '?' . http_build_query($params);
}
