<?php
/**
 * REST route provider contract.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Contracts;

/**
 * A component that registers its own REST API routes.
 *
 * Each endpoint controller owns the paths, argument schemas and callbacks it
 * knows best; the REST registrar simply collects providers and invokes them on
 * `rest_api_init`, so adding an endpoint never means editing the registrar.
 */
interface RouteProvider {

	/**
	 * Register this provider's routes with the REST API.
	 */
	public function register_routes(): void;
}
