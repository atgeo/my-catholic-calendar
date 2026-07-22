<?php
/**
 * Liturgical calendar gateway contract.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Contracts;

use MyCatholicCalendar\Api\CalendarQuery;
use MyCatholicCalendar\Exceptions\GatewayException;

/**
 * The single boundary between the plugin and the liturgical calendar data
 * source (LitCal). Everything in the plugin depends on this interface rather
 * than on the underlying HTTP client or vendor library, keeping the data
 * source swappable and the rest of the code testable.
 */
interface LitCalGateway {

	/**
	 * Fetch the liturgical calendar for a query.
	 *
	 * @param CalendarQuery $query The calendar to fetch.
	 * @return array<string,mixed> Normalized response with `litcal`, `settings`,
	 *                             `metadata` and `messages` keys.
	 * @throws GatewayException When the data cannot be retrieved or is malformed.
	 */
	public function calendar( CalendarQuery $query ): array;

	/**
	 * Fetch calendar metadata (available nations, dioceses and locales).
	 *
	 * @return array<string,mixed> The `litcal_metadata` structure.
	 * @throws GatewayException When the data cannot be retrieved or is malformed.
	 */
	public function metadata(): array;
}
