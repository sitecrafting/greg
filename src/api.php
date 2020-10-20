<?php

/**
 * Public API for the Greg plugin
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg;

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
