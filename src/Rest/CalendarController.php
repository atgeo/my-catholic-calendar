<?php
/**
 * Calendar REST endpoint.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Rest;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Kalenda\Api\CalendarQuery;
use Kalenda\Contracts\LitCalGateway;
use Kalenda\Contracts\RouteProvider;
use Kalenda\Exceptions\GatewayException;
use Kalenda\Support\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves `GET kalenda/v1/calendar`.
 *
 * Validates request arguments against the schema and the live metadata
 * allowlist, delegates to the gateway (which caches), and translates the two
 * domain failure modes into REST errors: invalid input -> 400, upstream
 * unavailable -> 502. It never talks to LitCal or HTTP directly.
 */
final class CalendarController implements RouteProvider {

	/**
	 * Wire the endpoint's collaborators.
	 *
	 * @param LitCalGateway $gateway Calendar data source.
	 * @param Options       $options Plugin defaults for unspecified arguments.
	 */
	public function __construct(
		private readonly LitCalGateway $gateway,
		private readonly Options $options
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRegistrar::REST_NAMESPACE,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar' ),
				'permission_callback' => '__return_true',
				'args'                => $this->args(),
			)
		);

		register_rest_route(
			RestRegistrar::REST_NAMESPACE,
			'/day',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_day' ),
				'permission_callback' => '__return_true',
				'args'                => $this->args(),
			)
		);
	}

	/**
	 * Argument schema for the calendar route.
	 *
	 * Defaults fall back to the site's configured calendar, so a bare request
	 * returns the administrator's chosen calendar for the current year.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function args(): array {
		return array(
			'type'        => array(
				'type'              => 'string',
				'enum'              => array(
					CalendarQuery::TYPE_GENERAL,
					CalendarQuery::TYPE_NATION,
					CalendarQuery::TYPE_DIOCESE,
				),
				'default'           => $this->options->calendar_type(),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'calendar_id' => array(
				'type'              => 'string',
				'default'           => $this->options->calendar_id(),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'year'        => array(
				'type'              => 'integer',
				'minimum'           => 1970,
				'maximum'           => 9999,
				'default'           => (int) current_time( 'Y' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'year_type'   => array(
				'type'              => 'string',
				'enum'              => array( CalendarQuery::YEAR_LITURGICAL, CalendarQuery::YEAR_CIVIL ),
				'default'           => $this->options->year_type(),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
			'locale'      => array(
				'type'              => 'string',
				'default'           => $this->options->locale(),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_calendar( WP_REST_Request $request ) {
		try {
			$query = CalendarQuery::create(
				(string) $request['type'],
				(string) $request['calendar_id'],
				(int) $request['year'],
				(string) $request['year_type'],
				(string) $request['locale']
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error(
				'rest_invalid_param',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		}

		try {
			$allowlist_error = $this->validate_calendar_id( $query );
			if ( $allowlist_error instanceof WP_Error ) {
				return $allowlist_error;
			}

			$data = $this->gateway->calendar( $query );
		} catch ( GatewayException $e ) {
			return new WP_Error(
				'kalenda_upstream_unavailable',
				__( 'The liturgical calendar service is currently unavailable. Please try again later.', 'kalenda' ),
				array( 'status' => 502 )
			);
		}

		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Return available calendar metadata.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @throws Exception When the requested date cannot be resolved.
	 */
	public function get_day( WP_REST_Request $request ) {
		$date = $this->resolve_date( $request );

		try {
			$query = CalendarQuery::create(
				(string) $request['type'],
				(string) $request['calendar_id'],
				(int) $date->format( 'Y' ),
				CalendarQuery::YEAR_CIVIL,
				(string) $request['locale']
			);

			$data = $this->gateway->calendar( $query );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error(
				'rest_invalid_param',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( GatewayException $e ) {
			return new WP_Error(
				'kalenda_upstream_unavailable',
				__( 'The liturgical calendar service is currently unavailable. Please try again later.', 'kalenda' ),
				array( 'status' => 502 )
			);
		}

		$day = $this->find_day( $data, $date );

		if ( null === $day ) {
			return new WP_Error(
				'kalenda_day_not_found',
				__( 'No liturgical information found for the requested date.', 'kalenda' ),
				array( 'status' => 404 )
			);
		}

		$response = new WP_REST_Response( $day );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Ensure a requested nation/diocese id actually exists.
	 *
	 * Checked against the live metadata allowlist rather than a hardcoded list,
	 * so newly published calendars work without a plugin update.
	 *
	 * @param CalendarQuery $query The query to check.
	 * @return WP_Error|null A 400 error when the id is unknown, otherwise null.
	 *
	 * @throws GatewayException When metadata cannot be retrieved.
	 */
	private function validate_calendar_id( CalendarQuery $query ): ?WP_Error {
		if ( CalendarQuery::TYPE_GENERAL === $query->type ) {
			return null;
		}

		$metadata = $this->gateway->metadata();

		$key = CalendarQuery::TYPE_NATION === $query->type
			? 'national_calendars_keys'
			: 'diocesan_calendars_keys';

		$allowed = isset( $metadata[ $key ] ) && is_array( $metadata[ $key ] ) ? $metadata[ $key ] : array();

		if ( in_array( $query->calendar_id, $allowed, true ) ) {
			return null;
		}

		return new WP_Error(
			'rest_invalid_param',
			sprintf(
				/* translators: 1: calendar type (nation or diocese), 2: requested id. */
				__( 'Unknown %1$s calendar: %2$s.', 'kalenda' ),
				$query->type,
				$query->calendar_id
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Resolve the requested date or default to today in the site timezone.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return DateTimeImmutable
	 * @throws Exception When the supplied date cannot be parsed.
	 */
	private function resolve_date( WP_REST_Request $request ): DateTimeImmutable {
		if ( empty( $request['date'] ) ) {
			return current_datetime();
		}

		return new DateTimeImmutable(
			(string) $request['date'],
			wp_timezone()
		);
	}

	/**
	 * Find the liturgical information for a single day.
	 *
	 * @param array<string,mixed> $calendar Calendar response.
	 * @param DateTimeInterface   $date     Requested day.
	 * @return array<string,mixed>|null
	 */
	private function find_day( array $calendar, DateTimeInterface $date ): ?array {
		$wanted = $date->format( 'Y-m-d' );

		foreach ( $calendar['litcal'] ?? array() as $event ) {
			if (
				isset( $event['date'] ) &&
				str_starts_with( (string) $event['date'], $wanted )
			) {
				return $event;
			}
		}

		return null;
	}
}
