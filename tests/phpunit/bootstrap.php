<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require __DIR__ . '/wp-stubs.php';

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
