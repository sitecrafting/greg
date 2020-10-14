<?php
/**
 * Base class for Greg unit test cases
 *
 * @copyright 2020 SiteCrafting, Inc.
 * @author    Coby Tamayo <ctamayo@sitecrafting.com>
 */

namespace Greg\Unit;

use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Base test class for the unit test suite. Declared abstract so that PHPUnit
 * doesn't complain about a lack of tests defined here.
 */
abstract class BaseTest extends TestCase {
  public function setUp() : void {
    WP_Mock::setUp();
  }

  public function tearDown() : void {
    WP_Mock::tearDown();
  }
}
