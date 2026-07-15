<?php
/**
 * Calendar REST endpoint.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Rest;

use DateTimeImmutable;
use Exception as DateParseException;
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
				'args'                => $this->calendar_args(),
			)
		);

		register_rest_route(
			RestRegistrar::REST_NAMESPACE,
			'/day',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_day' ),
				'permission_callback' => '__return_true',
				'args'                => $this->day_args(),
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
	private function calendar_args(): array {
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
	 * Argument schema for `/day`.
	 *
	 * Same as {@see calendar_args()} but with `year`/`year_type` replaced by
	 * `date`: the year is derived from the resolved date and the year type is
	 * always CIVIL, so exposing them here would just be two params clients
	 * could set that silently do nothing.
	 *
	 * `date` is restricted to a bare `Y-m-d` string on purpose: accepting any
	 * string `DateTimeImmutable` can parse would let a client-supplied
	 * timezone offset (e.g. `2026-01-01T00:00:00+05:00`) override the site
	 * timezone `resolve_date()` applies, which is exactly the off-by-one this
	 * route needs to avoid.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function day_args(): array {
		$args = $this->calendar_args();
		unset( $args['year'], $args['year_type'] );

		$args['date'] = array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => static function ( mixed $value ): bool {
				if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
					return false;
				}

				// Reject well-formed but non-existent dates (e.g. 2026-02-30),
				// which DateTimeImmutable would otherwise silently roll over.
				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
			},
		);

		return $args;
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_calendar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$query = CalendarQuery::create(
				(string) $request['type'],
				(string) $request['calendar_id'],
				(int) $request['year'],
				(string) $request['year_type'],
				(string) $request['locale']
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->invalid_param_error( $e->getMessage() );
		}

		$data = $this->fetch( $query );

		return $data instanceof WP_Error
			? $data
			: $this->cached_response( $data, $this->year_max_age( $query->year ) );
	}

	/**
	 * Handle `GET /day`.
	 *
	 * Resolves the requested (or today's) date in the site timezone, fetches
	 * the CIVIL-year calendar for that date's year, and returns the same
	 * `litcal`/`settings`/`metadata`/`messages` envelope as `/calendar` with
	 * `litcal` filtered down to that day — possibly more than one entry (a
	 * vigil Mass and the next day's feast can share a date), possibly none.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_day( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$date = $this->resolve_date( $request );
		} catch ( DateParseException $e ) {
			return $this->invalid_param_error( __( 'The requested date could not be parsed.', 'kalenda' ) );
		}

		try {
			$query = CalendarQuery::create(
				(string) $request['type'],
				(string) $request['calendar_id'],
				(int) $date->format( 'Y' ),
				CalendarQuery::YEAR_CIVIL,
				(string) $request['locale']
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->invalid_param_error( $e->getMessage() );
		}

		$data = $this->fetch( $query );
		if ( $data instanceof WP_Error ) {
			return $data;
		}

		$data['litcal'] = $this->events_on( (array) ( $data['litcal'] ?? array() ), $date->format( 'Y-m-d' ) );

		return $this->cached_response( $data, $this->day_max_age( $date ) );
	}

	/**
	 * Run a validated query through the allowlist and the gateway.
	 *
	 * Shared by both routes: performs the metadata allowlist check, then fetches
	 * (and caches, in the gateway) the calendar, translating an unavailable
	 * upstream into a 502.
	 *
	 * @param CalendarQuery $query The query to run.
	 * @return array<string,mixed>|WP_Error Gateway data, or an error response.
	 */
	private function fetch( CalendarQuery $query ): array|WP_Error {
		$allowlist_error = $this->check_allowlist( $query );
		if ( null !== $allowlist_error ) {
			return $allowlist_error;
		}

		try {
			return $this->gateway->calendar( $query );
		} catch ( GatewayException $e ) {
			return $this->upstream_error();
		}
	}

	/**
	 * Validate a query's calendar id and locale against the live metadata
	 * allowlist. Checked separately from {@see CalendarQuery}'s own structural
	 * validation because membership requires a network round trip the value
	 * object has no business making.
	 *
	 * @param CalendarQuery $query The query to check.
	 * @return WP_Error|null A 400 error when invalid, null when allowed
	 *                       (including when the allowlist itself is unavailable —
	 *                       we degrade gracefully rather than block on it).
	 */
	private function check_allowlist( CalendarQuery $query ): ?WP_Error {
		try {
			$allowlist = new MetadataAllowlist( $this->gateway->metadata() );
		} catch ( GatewayException $e ) {
			return null;
		}

		if ( CalendarQuery::TYPE_GENERAL !== $query->type
			&& ! $allowlist->is_valid_calendar_id( $query->type, (string) $query->calendar_id )
		) {
			return $this->invalid_param_error(
				sprintf(
				/* translators: 1: calendar type (nation or diocese), 2: requested id. */
					__( 'Unknown %1$s calendar: %2$s.', 'kalenda' ),
					$query->type,
					(string) $query->calendar_id
				)
			);
		}

		if ( ! $allowlist->is_valid_locale( $query->type, $query->calendar_id, $query->locale ) ) {
			return $this->invalid_param_error(
				sprintf(
				/* translators: %s: the unsupported locale code. */
					__( 'Unsupported locale for this calendar: %s', 'kalenda' ),
					$query->locale
				)
			);
		}

		return null;
	}

	/**
	 * Resolve the requested date, or default to today in the site timezone.
	 *
	 * `date` is already constrained to `Y-m-d` by the `/day` arg schema, so
	 * this never receives a string carrying its own timezone/offset — the
	 * explicit {@see wp_timezone()} always wins.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return DateTimeImmutable
	 *
	 * @throws DateParseException When the supplied date cannot be parsed (should not
	 *                            happen given the schema's validate_callback, but the
	 *                            constructor call is not itself typed to guarantee it).
	 */
	private function resolve_date( WP_REST_Request $request ): DateTimeImmutable {
		if ( empty( $request['date'] ) ) {
			return current_datetime();
		}

		return new DateTimeImmutable( (string) $request['date'], wp_timezone() );
	}

	/**
	 * Every event in a `litcal` array falling on a given date.
	 *
	 * A single date can carry more than one entry (a vigil Mass and the next
	 * day's feast, optional memorials, etc.), so this returns all matches
	 * rather than the first.
	 *
	 * @param array<int,mixed> $events Full year's events, as returned by the gateway.
	 * @param string           $date   The `Y-m-d` date to keep.
	 * @return array<int,mixed>
	 */
	private function events_on( array $events, string $date ): array {
		return array_values(
			array_filter(
				$events,
				static function ( mixed $event ) use ( $date ): bool {
					$event_date = is_array( $event ) ? ( $event['date'] ?? null ) : null;

					return is_string( $event_date ) && str_starts_with( $event_date, $date );
				}
			)
		);
	}

	/**
	 * Wrap gateway data in a response with a Cache-Control header, so HTTP
	 * caches/CDNs can help absorb load on top of the gateway's own transient cache.
	 *
	 * @param array<string,mixed> $data    Gateway response body.
	 * @param int                 $max_age Public cache lifetime in seconds.
	 * @return WP_REST_Response
	 */
	private function cached_response( array $data, int $max_age ): WP_REST_Response {
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', sprintf( 'public, max-age=%d', max( 0, $max_age ) ) );

		return $response;
	}

	/**
	 * HTTP max-age for a calendar year, mirroring the gateway's cache tiering:
	 * past years are liturgically immutable and can be cached hard, while the
	 * current and future years use the configurable default.
	 *
	 * @param int $year Calendar year.
	 * @return int Seconds.
	 */
	private function year_max_age( int $year ): int {
		return $year < (int) current_datetime()->format( 'Y' )
			? YEAR_IN_SECONDS
			: max( 0, $this->options->cache_ttl() );
	}

	/**
	 * HTTP max-age for a `/day` response.
	 *
	 * An explicit past/future date follows the year tiering. For *today*, the
	 * value is capped at the time left until midnight in the site timezone, so a
	 * cache never keeps serving today's celebration once the day has rolled over.
	 *
	 * @param DateTimeImmutable $date The resolved date.
	 * @return int Seconds.
	 */
	private function day_max_age( DateTimeImmutable $date ): int {
		$now = current_datetime();

		if ( $date->format( 'Y-m-d' ) !== $now->format( 'Y-m-d' ) ) {
			return $this->year_max_age( (int) $date->format( 'Y' ) );
		}

		$until_midnight = $now->modify( 'tomorrow midnight' )->getTimestamp() - $now->getTimestamp();

		return max( 0, min( $this->options->cache_ttl(), $until_midnight ) );
	}

	/**
	 * A 400 error using WP core's own arg-validation error code, so REST
	 * consumers see one consistent code whether WP's schema validation or our
	 * domain validation caught the problem.
	 *
	 * @param string $message Human-readable, translatable error message.
	 * @return WP_Error
	 */
	private function invalid_param_error( string $message ): WP_Error {
		return new WP_Error( 'rest_invalid_param', $message, array( 'status' => 400 ) );
	}

	/**
	 * A 502 error for an unavailable upstream. Never includes the caught
	 * exception's own message — it may embed transport details not meant for
	 * API consumers.
	 *
	 * @return WP_Error
	 */
	private function upstream_error(): WP_Error {
		return new WP_Error(
			'kalenda_upstream_unavailable',
			__( 'The liturgical calendar service is currently unavailable. Please try again later.', 'kalenda' ),
			array( 'status' => 502 )
		);
	}
}
