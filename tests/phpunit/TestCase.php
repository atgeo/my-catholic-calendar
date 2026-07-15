<?php
/**
 * Base test case.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Tests;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Wires Brain Monkey's function mocking into every test's fixture lifecycle.
 *
 * Uses the polyfill's `set_up()`/`tear_down()` hooks (not `setUp()`/`tearDown()`
 * directly) so this stays compatible across the PHPUnit versions the polyfill
 * itself supports.
 */
abstract class TestCase extends PolyfillTestCase {

	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
	}

	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}
}
