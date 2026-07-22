<?php
/**
 * Kalenda
 *
 * @package           MyCatholicCalendar
 * @author            Georges Kmeid
 * @copyright         2026 Georges Kmeid
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MyCatholicCalendar
 * Plugin URI:        https://github.com/atgeo/my-catholic-calendar
 * Description:       Displays the Catholic liturgical calendar on any WordPress theme, powered by the LitCal API.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Georges Kmeid
 * Author URI:        https://github.com/atgeo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-catholic-calendar
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace MyCatholicCalendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Prevent double-loading (e.g. plugin present in two locations).
if ( defined( 'MyCatholicCalendar\\MY_CATHOLIC_CALENDAR_VERSION' ) ) {
	return;
}

const MY_CATHOLIC_CALENDAR_VERSION     = '0.1.0';
const MY_CATHOLIC_CALENDAR_MINIMUM_PHP = '8.1';
const MY_CATHOLIC_CALENDAR_MINIMUM_WP  = '6.5';

define( 'MyCatholicCalendar\\MY_CATHOLIC_CALENDAR_FILE', __FILE__ );
define( 'MyCatholicCalendar\\MY_CATHOLIC_CALENDAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MyCatholicCalendar\\MY_CATHOLIC_CALENDAR_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'my_catholic_calendar' ) ) {
	/**
	 * Retrieve the Kalenda plugin instance.
	 */
	function my_catholic_calendar(): Plugin {
		return Plugin::instance();
	}
}

/**
 * Render a dismissible admin notice describing why the plugin could not boot.
 *
 * @param string $message Already-translated, plain-text message.
 */
function admin_notice( string $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'My Catholic Calendar:', 'my-catholic-calendar' ),
				esc_html( $message )
			);
		}
	);
}

/**
 * Verify the runtime meets the minimum requirements before booting.
 *
 * @return bool True when the environment is supported.
 */
function requirements_met(): bool {
	if ( version_compare( PHP_VERSION, MY_CATHOLIC_CALENDAR_MINIMUM_PHP, '<' ) ) {
		admin_notice(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'requires PHP %1$s or newer. You are running PHP %2$s.', 'my-catholic-calendar' ),
				MY_CATHOLIC_CALENDAR_MINIMUM_PHP,
				PHP_VERSION
			)
		);
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), MY_CATHOLIC_CALENDAR_MINIMUM_WP, '<' ) ) {
		admin_notice(
			sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'requires WordPress %1$s or newer. Please update WordPress.', 'my-catholic-calendar' ),
				MY_CATHOLIC_CALENDAR_MINIMUM_WP,
				get_bloginfo( 'version' )
			)
		);
		return false;
	}

	if ( ! is_readable( MY_CATHOLIC_CALENDAR_PATH . 'vendor/autoload.php' ) ) {
		admin_notice(
			__( 'is missing its Composer dependencies. Run "composer install" in the plugin directory.', 'my-catholic-calendar' )
		);
		return false;
	}

	return true;
}

// Bail early (without fataling) when the environment is unsupported.
if ( ! requirements_met() ) {
	return;
}

require_once MY_CATHOLIC_CALENDAR_PATH . 'vendor/autoload.php';

/**
 * Boot the plugin once WordPress and all other plugins have loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		my_catholic_calendar()->boot();
	}
);
