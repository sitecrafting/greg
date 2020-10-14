<?php

/**
 * Greg\WpCli\GregCommand class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\WpCli;

use WP_CLI;

/**
 * Class for implementing all WP-CLI `greg` subcommands.
 */
class GregCommand {
  /**
   * DO A THING
   *
   * ## OPTIONS
   *
   * <arg>
   * : Describe your arg here
   *
   * [--option=<option>]
   * : Describe your option
   * ---
   * default: 100
   * ---
   *
   * [--flag]
   * : Describe your flag
   * ---
   *
   * ## EXAMPLES
   *
   *     wp greg thing abc
   *     wp greg thing --option=foo xyz
   *     wp greg thing --flag blah
   *
   * @subcommand thing
   * @when after_wp_load
   */
  public function thing(array $args, array $options) {
    WP_CLI::success(sprintf('You passed: %s', $args[0]));
  }
}
