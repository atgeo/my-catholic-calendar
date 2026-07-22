<?php
/**
 * Uninstall routine.
 *
 * Runs when the user deletes MyCatholicCalendar from the WordPress admin. Removes the
 * plugin's stored options and any cached liturgical data (transients).
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

// Bail unless WordPress is genuinely uninstalling this plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin data for a single site.
 */
function my_catholic_calendar_uninstall_site(): void {
	global $wpdb;

	delete_option( 'my_catholic_calendar_settings' );

	// Delete every transient (and its timeout) created by the plugin.
	// Transient keys are prefixed with "my_catholic_calendar_"; object caches are flushed
	// separately below since they are not stored in the options table.
	$like = $wpdb->esc_like( '_transient_my_catholic_calendar_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$like_timeout = $wpdb->esc_like( '_transient_timeout_my_catholic_calendar_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	wp_cache_flush();
}

if ( is_multisite() ) {
	$my_catholic_calendar_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $my_catholic_calendar_site_ids as $my_catholic_calendar_site_id ) {
		switch_to_blog( (int) $my_catholic_calendar_site_id );
		my_catholic_calendar_uninstall_site();
		restore_current_blog();
	}
} else {
	mcc_uninstall_site();
}
