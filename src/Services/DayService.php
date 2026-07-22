<?php
/**
 * Day service.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Services;

use DateTimeImmutable;

/**
 * Retrieves the events for a single day.
 */
final class DayService {
	/**
	 * Every event in a `litcal` array falling on a given date.
	 *
	 * A single date can carry more than one entry (a vigil Mass and the next
	 * day's feast, optional memorials, etc.), so this returns all matches
	 * rather than the first.
	 *
	 * @param array<int,mixed>  $events Full year's events, as returned by the gateway.
	 * @param DateTimeImmutable $date The `Y-m-d` date to keep.
	 *
	 * @return array<int,mixed>
	 */
	public function filter(
		array $events,
		DateTimeImmutable $date
	): array {
		$day = $date->format( 'Y-m-d' );

		return array_values(
			array_filter(
				$events,
				static function ( mixed $event ) use ( $day ): bool {
					$event_date = is_array( $event ) ? ( $event['date'] ?? null ) : null;

					return is_string( $event_date ) && str_starts_with( $event_date, $day );
				}
			)
		);
	}
}
