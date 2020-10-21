<?php

/**
 * Public API for the Greg plugin
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

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
  $params = array_merge([
    'current_time' => gmdate('Y-m-d H:i:s'),
    // TODO meta_keys here
  ], $params);

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
    // TODO abstract this out into a class
    $events = array_map(function($event) : array {
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
        // TODO use meta_keys filter
        'start'                  => $event->meta('start_date'),
        'end'                    => $event->meta('end_date'),
        'title'                  => $event->title(),
        'recurrence'             => $recurrence_rules,
        'recurrence_description' => $event->meta('recurrence_description'),
        'post'                   => $event,
      ];
    }, $events->to_array());

    $calendar = new Calendar($events);

    return array_map([Event::class, 'from_array'], $calendar->recurrences());
  } else {
    return array_map([Event::class, 'from_post'], $events->to_array());
  }
}
