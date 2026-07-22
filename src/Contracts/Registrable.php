<?php
/**
 * Registrable service contract.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Contracts;

/**
 * A service that hooks itself into WordPress.
 *
 * Implementations wire their own actions and filters inside {@see register()};
 * the plugin container calls this once during boot.
 */
interface Registrable {

	/**
	 * Register the service's hooks with WordPress.
	 */
	public function register(): void;
}
