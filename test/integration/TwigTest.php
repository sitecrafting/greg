<?php

/**
 * Greg\Integration\TwigTest class
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Integration;

use Timber\Timber;

/**
 * Test case for Greg's Twig integration
 *
 * @group integration
 */
class TwigTest extends IntegrationTest {
  public function test_greg_event_month() {
    $this->markTestSkipped();
    set_query_var('event_month', '2020-05');
    $this->assertEquals('May 2020', Timber::compile_string(
      '{{ greg_event_month("F Y") }}'
    ));
  }
}
