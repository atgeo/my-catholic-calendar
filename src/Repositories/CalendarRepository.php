<?php
/**
 * Calendar repository.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Repositories;

use MyCatholicCalendar\Api\CalendarQuery;
use MyCatholicCalendar\Contracts\LitCalGateway;
use MyCatholicCalendar\Exceptions\GatewayException;
use MyCatholicCalendar\Rest\MetadataAllowlist;
use WP_Error;

/**
 * Calendar repository.
 */
final class CalendarRepository {
	/**
	 * Constructor.
	 *
	 * @param LitCalGateway $gateway Calendar data source.
	 */
	public function __construct(
		private readonly LitCalGateway $gateway
	) {}

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
	public function fetch( CalendarQuery $query ): array|WP_Error {
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
					__( 'Unknown %1$s calendar: %2$s.', 'my-catholic-calendar' ),
					$query->type,
					(string) $query->calendar_id
				)
			);
		}

		if ( ! $allowlist->is_valid_locale( $query->type, $query->calendar_id, $query->locale ) ) {
			return $this->invalid_param_error(
				sprintf(
				/* translators: %s: the unsupported locale code. */
					__( 'Unsupported locale for this calendar: %s', 'my-catholic-calendar' ),
					$query->locale
				)
			);
		}

		return null;
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
			'mcc_upstream_unavailable',
			__( 'The liturgical calendar service is currently unavailable. Please try again later.', 'my-catholic-calendar' ),
			array( 'status' => 502 )
		);
	}
}
