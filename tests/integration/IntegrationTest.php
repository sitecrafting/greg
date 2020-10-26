<?php

/**
 * Base class for Greg integration test cases
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Integration;

use WP_UnitTestCase;

use Greg\Event;

/**
 * Base test class for the plugin. Declared abstract so that PHPUnit doesn't
 * complain about a lack of tests defined here.
 */
abstract class IntegrationTest extends WP_UnitTestCase {
  public function setUp() {
    parent::setUp();
    $this->setup_default_meta_keys();
  }

  protected function setup_default_meta_keys() {
    add_filter('greg/meta_keys', function() : array {
      return Event::DEFAULT_META_KEYS;
    });
  }
}
