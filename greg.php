<?php

/**
 * Plugin Name: Greg
 * Plugin URI: https://github.com/sitecrafting/greg
 * Author: SiteCrafting
 * Author URI: https://www.sitecrafting.com/
 * Description: A de-coupled calendar solution for WordPress and Timber
 * Version: 0.2.1
 * Requires PHP: 7.4
 */


// no script kiddiez
if (!defined('ABSPATH')) {
  return;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

use Timber\Timber;
use Twig\TwigFunction;
use Twig\Environment;

use Greg\Event;
use Greg\Rest\RestController;
use Greg\WpCli\GregCommand;

define('GREG_PLUGIN_WEB_PATH', plugin_dir_url(__FILE__));
define('GREG_PLUGIN_JS_ROOT', GREG_PLUGIN_WEB_PATH . 'js');
define('GREG_PLUGIN_VIEW_PATH', __DIR__ . '/views');


add_action('init', function() {
  register_post_type('greg_event', [
    'public'                  => true,
    'description'             => 'Calendar Events, potentially with recurrences',
    'hierarchical'            => false,
    'rewrite'                 => [
      'slug'                  => 'event',
    ],
    'labels'                  => [
      'name'                  => 'Events',
      'singular_name'         => 'Event',
      'add_new_item'          => 'Schedule New Event',
      'edit_item'             => 'Edit Event',
      'new_item'              => 'New Event',
      'view_item'             => 'View Event',
      'view_items'            => 'View Events',
      'search_items'          => 'Search Events',
      'not_found'             => 'No Events found',
      'not_found_in_trash'    => 'No Events found in trash',
      'all_items'             => 'All Events',
      'archives'              => 'Event Archives',
      'attributes'            => 'Event Attributes',
      'insert_into_item'      => 'Insert into Event',
      'uploaded_to_this_item' => 'Uploaded to this Event',
    ],
    'menu_icon'               => 'dashicons-calendar',
    'has_archive'             => true, // TODO do we want this?
  ]);

  register_taxonomy('greg_event_category', ['greg_event'], [
    'public'                       => true,
    'rewrite'                      => [
      'slug'                       => 'event-category',
    ],
    'labels'                       => [
      'name'                       => 'Event Categories',
      'singular_name'              => 'Event',
      'menu_name'                  => 'Event Categories',
      'all_items'                  => 'All Event Categories',
      'edit_item'                  => 'Edit Event',
      'view_item'                  => 'View Event',
      'update_item'                => 'Update Event',
      'add_new_item'               => 'Add New Event',
      'new_item_name'              => 'New Event Name',
      'parent_item'                => 'Parent Event',
      'parent_item_colon'          => 'Parent Event:',
      'search_items'               => 'Search Event Categories',
      'popular_items'              => 'Popular Event Categories',
      'separate_items_with_commas' => 'Separate Event Categories with commas',
      'add_or_remove_items'        => 'Add or remove Event Categories',
      'choose_from_most_used'      => 'Choose from the most used Event Categories',
      'not_found'                  => 'No Event Categories found',
      'back_to_items'              => '← Back to Event Categories',
    ],
  ]);

  $GLOBALS['wp']->add_query_var('event_month');
});


/**
 * Set up default meta_keys
 */
add_filter('greg/meta_keys', function() : array {
  return Event::DEFAULT_META_KEYS;
});

/**
 * Set up default params.
 */
add_filter('greg/params', function(array $params) : array {
  // Defaults.
  $params = array_merge([
    'current_time' => gmdate('Y-m-d H:i:s'),
    'meta_keys'    => apply_filters('greg/meta_keys', []),
    // Unless explicitly passed, keep event_month unset.
    'event_month'  => get_query_var('event_month') ?: null,
  ], $params);

  // Query by current event category on Greg archive pages.
  global $wp_query;
  if (
    empty($params['event_category']) &&
    $wp_query->is_tax() &&
    !empty($wp_query->tax_query->queried_terms['greg_event_category'])
  ) {
    $params['event_category'] = Timber::get_term()->id ?? null;
  }

  return $params;
});


/*
 * Add REST Routes
 */
add_action('rest_api_init', function() {
  $controller = new RestController();
  $controller->register_routes();
});


/*
 * Add WP-CLI tooling
 */
if (defined('WP_CLI') && WP_CLI) {
  $command = new GregCommand();
  WP_CLI::add_command('greg', $command);
}

/**
 * Merges in default data for the event-categories-list.twig view.
 */
add_filter('greg/render/event-categories-list.twig', function(array $data) : array {
  $data['term']  = $data['term'] ?? Timber::get_term();
  $data['terms'] = $data['terms'] ?? Timber::get_terms([
    'taxonomy'   => 'greg_event_category',
  ]);

  return $data;
});

/**
 * Merges in default data for the event-categories-list.twig view.
 */
add_filter('greg/render/events-list.twig', function(array $data) : array {
  $data['params'] = $data['params'] ?? [];
  $data['events'] = $data['events'] ?? Greg\get_events($data['params']);

  return $data;
});

add_filter('timber/locations', function(array $paths) {
  $paths['greg'] = array_filter([
    get_template_directory() . '/views/greg',
    GREG_PLUGIN_VIEW_PATH . '/twig',
  ], 'is_dir');

  return $paths;
});

add_filter('timber/twig', function(Environment $twig) {
  $twig->addFunction(new TwigFunction('greg_compile', Greg\compile::class));
  $twig->addFunction(new TwigFunction('greg_render', Greg\render::class));
  $twig->addFunction(new TwigFunction('greg_get_events', Greg\get_events::class));
  $twig->addFunction(new TwigFunction('greg_event_month', Greg\event_month::class));
  $twig->addFunction(new TwigFunction('greg_prev_month', Greg\prev_month::class));
  $twig->addFunction(new TwigFunction('greg_next_month', Greg\next_month::class));
  $twig->addFunction(new TwigFunction('greg_event_category', Greg\event_category::class));
  $twig->addFunction(new TwigFunction('greg_prev_month_query_string', Greg\prev_month_query_string::class));
  $twig->addFunction(new TwigFunction('greg_next_month_query_string', Greg\next_month_query_string::class));

  return $twig;
});
