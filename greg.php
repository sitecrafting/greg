<?php

/**
 * Plugin Name: Greg
 * Author: SiteCrafting
 * Author URI: https://www.sitecrafting.com/
 * Description: A de-coupled calendar solution for WordPress and Timber
 * Version: 0.0.1
 * Requires PHP: 7.4
 */


// no script kiddiez
if (!defined('ABSPATH')) {
  return;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

use Greg\Rest\RestController;
use Greg\WpCli\GregCommand;

define('GREG_PLUGIN_WEB_PATH', plugin_dir_url(__FILE__));
define('GREG_PLUGIN_JS_ROOT', GREG_PLUGIN_WEB_PATH . 'js');
define('GREG_PLUGIN_VIEW_PATH', __DIR__ . '/views');


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

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_script(
    'greg',
    GREG_PLUGIN_WEB_PATH . 'js/greg.js',
    [], // deps
    false, // default to current WP version
    true // render in footer
  );
});

add_action('wp_enqueue_scripts', function() {
  wp_enqueue_script(
    'greg',
    GREG_PLUGIN_WEB_PATH . 'js/greg.js',
    [], // deps
    false, // default to current WP version
    true // render in footer
  );
});

/**
 * Example:
 *
 * apply_filters('greg/render', ['my_string' => 'Hello, World!']);
 */
add_filter('greg/render', function($tpl, $data = []) {
  // Allow for theme overrides
  $path = get_template_directory() . '/greg/' . $tpl;

  if (!file_exists($path)) {
    $path = GREG_PLUGIN_VIEW_PATH . $tpl;
  }

  if (file_exists($path)) {
    ob_start();
    require $path;
    return ob_get_clean();
  }
}, 10, 2);

