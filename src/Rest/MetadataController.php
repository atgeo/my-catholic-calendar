<?php
/**
 * Metadata REST endpoint.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Rest;

use MyCatholicCalendar\Contracts\LitCalGateway;
use MyCatholicCalendar\Contracts\RouteProvider;
use MyCatholicCalendar\Exceptions\GatewayException;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves `GET my-catholic-calendar/v1/calendars`.

 * Returns the LitCal metadata describing the available national and diocesan
 * calendars. The gateway handles caching; this controller only exposes the
 * data over the REST API.
 */
final class MetadataController implements RouteProvider {

	/**
	 * Constructor.
	 *
	 * @param LitCalGateway $gateway Calendar data source.
	 */
	public function __construct(
		private readonly LitCalGateway $gateway
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRegistrar::REST_NAMESPACE,
			'/calendars',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_metadata' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return available calendar metadata.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_metadata() {
		try {
			$data = $this->gateway->metadata();
		} catch ( GatewayException $e ) {
			return new WP_Error(
				'mcc_upstream_unavailable',
				__( 'The liturgical calendar service is currently unavailable. Please try again later.', 'my-catholic-calendar' ),
				array( 'status' => 502 )
			);
		}

		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}
}
