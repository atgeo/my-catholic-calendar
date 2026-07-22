<?php
/**
 * Transient-backed cache.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Cache;

use MyCatholicCalendar\Contracts\Cache;

/**
 * Caches values in WordPress transients.
 *
 * Transients transparently use a persistent object cache (Redis, Memcached)
 * when one is available, and fall back to the options table otherwise, so this
 * is both fast on managed hosts and dependency-free on shared hosting.
 *
 * All keys are expected to carry the plugin's `mcc_` prefix so that
 * {@see flush()} and the uninstall routine can find them.
 */
final class TransientCache implements Cache {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Cache key.
	 * @return mixed
	 */
	public function get( string $key ): mixed {
		$value = get_transient( $key );

		return false === $value ? null : $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds.
	 */
	public function set( string $key, mixed $value, int $ttl ): void {
		set_transient( $key, $value, $ttl );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): void {
		delete_transient( $key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Removes every `mcc_`-prefixed transient. WordPress has no API to
	 * delete transients by prefix, so a direct query is required; the object
	 * cache is flushed separately for hosts that keep transients out of the DB.
	 */
	public function flush(): void {
		global $wpdb;

		$like         = $wpdb->esc_like( '_transient_mcc_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_mcc_' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		wp_cache_flush();
	}
}
