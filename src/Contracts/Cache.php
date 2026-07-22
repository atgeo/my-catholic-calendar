<?php
/**
 * Cache contract.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Contracts;

/**
 * A minimal key/value cache.
 *
 * Deliberately smaller than PSR-16: the plugin owns caching internally and
 * never hands this to a third party, so only the operations actually used are
 * exposed. Implementations are expected to namespace their keys.
 */
interface Cache {

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed The cached value, or null when the key is absent.
	 */
	public function get( string $key ): mixed;

	/**
	 * Store a value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds.
	 */
	public function set( string $key, mixed $value, int $ttl ): void;

	/**
	 * Delete a single cached value.
	 *
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): void;

	/**
	 * Remove every value owned by the plugin.
	 */
	public function flush(): void;
}
