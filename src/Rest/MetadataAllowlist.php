<?php
/**
 * Metadata-backed calendar allowlist.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Rest;

use MyCatholicCalendar\Api\CalendarQuery;

/**
 * Validates calendar ids and locales against the live `/calendars` metadata.
 *
 * The LitCal metadata response is the only source of truth for which nation
 * and diocese ids currently exist and which locales each calendar supports —
 * {@see CalendarQuery} only checks structure, never membership, since that
 * would require a network round trip the value object has no business making.
 *
 * The exact shape of the upstream metadata response is not part of any
 * published contract, so extraction here tolerates a couple of plausible
 * shapes (a flat list of id strings, or a list of objects carrying a
 * `calendar_id` property) rather than assuming one. If the live API returns a
 * shape neither form recognises, lookups fall back to "unknown" — id checks
 * then reject the request (fail closed), while locale checks let it through
 * (fail open), since we would rather trust CalendarQuery's own validation
 * than block every request on a metadata field we failed to parse. Re-check
 * this against a live `/calendars` response if it ever needs adjusting.
 */
final class MetadataAllowlist {

	/**
	 * Candidate top-level keys for the national calendars collection, tried in order.
	 */
	private const NATIONAL_KEYS = array( 'national_calendars', 'national_calendars_keys' );

	/**
	 * Candidate top-level keys for the diocesan calendars collection, tried in order.
	 */
	private const DIOCESAN_KEYS = array( 'diocesan_calendars', 'diocesan_calendars_keys' );

	/**
	 * Candidate top-level keys for the General Roman Calendar's locale list.
	 */
	private const GENERAL_LOCALE_KEYS = array( 'locales' );

	/**
	 * Wrap a metadata response.
	 *
	 * @param array<string,mixed> $metadata The {@see \MyCatholicCalendar\Contracts\LitCalGateway::metadata()} response.
	 */
	public function __construct(
		private readonly array $metadata
	) {}

	/**
	 * Whether a calendar id is currently known for the given type.
	 *
	 * @param string $type        CalendarQuery::TYPE_NATION or CalendarQuery::TYPE_DIOCESE.
	 * @param string $calendar_id The id to check.
	 * @return bool
	 */
	public function is_valid_calendar_id( string $type, string $calendar_id ): bool {
		return in_array( $calendar_id, $this->calendar_ids( $type ), true );
	}

	/**
	 * Whether a locale is supported by the given calendar.
	 *
	 * @param string      $type        One of the CalendarQuery::TYPE_* constants.
	 * @param string|null $calendar_id Nation code or diocese id; null for the general calendar.
	 * @param string      $locale      Locale code to check.
	 * @return bool
	 */
	public function is_valid_locale( string $type, ?string $calendar_id, string $locale ): bool {
		$locales = $this->locales_for( $type, $calendar_id );

		// An empty list means we could not determine supported locales (e.g. an
		// unrecognised metadata shape); do not block the request on it.
		if ( array() === $locales ) {
			return true;
		}

		foreach ( $locales as $supported ) {
			if ( $this->locale_matches( $locale, $supported ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a requested locale is compatible with a supported one.
	 *
	 * Matches on the primary language subtag so request granularity need not
	 * equal the metadata's: general calendars advertise language codes (`en`)
	 * while national/diocesan calendars advertise full locales (`en_US`), and
	 * LitCal itself falls back within a language. A plain `en` is therefore
	 * accepted for a calendar offering `en_US`, but `fr` is still rejected for an
	 * English-only calendar.
	 *
	 * @param string $requested Requested locale.
	 * @param string $supported A locale the calendar advertises.
	 * @return bool
	 */
	private function locale_matches( string $requested, string $supported ): bool {
		return 0 === strcasecmp( $requested, $supported )
			|| 0 === strcasecmp( $this->language_subtag( $requested ), $this->language_subtag( $supported ) );
	}

	/**
	 * The primary language subtag of a locale (the part before "_" or "-").
	 *
	 * @param string $locale Locale code.
	 * @return string
	 */
	private function language_subtag( string $locale ): string {
		$parts = preg_split( '/[_-]/', $locale, 2 );

		return ( false !== $parts && isset( $parts[0] ) ) ? $parts[0] : $locale;
	}

	/**
	 * Known calendar ids for a nation or diocese type.
	 *
	 * @param string $type CalendarQuery::TYPE_NATION or CalendarQuery::TYPE_DIOCESE.
	 * @return string[]
	 */
	private function calendar_ids( string $type ): array {
		$keys = CalendarQuery::TYPE_NATION === $type ? self::NATIONAL_KEYS : self::DIOCESAN_KEYS;

		foreach ( $keys as $key ) {
			if ( isset( $this->metadata[ $key ] ) && is_array( $this->metadata[ $key ] ) ) {
				return $this->pluck_ids( $this->metadata[ $key ] );
			}
		}

		return array();
	}

	/**
	 * Supported locales for a calendar, or an empty array when unknown.
	 *
	 * @param string      $type        One of the CalendarQuery::TYPE_* constants.
	 * @param string|null $calendar_id Nation code or diocese id; null for the general calendar.
	 * @return string[]
	 */
	private function locales_for( string $type, ?string $calendar_id ): array {
		if ( CalendarQuery::TYPE_GENERAL === $type || null === $calendar_id ) {
			foreach ( self::GENERAL_LOCALE_KEYS as $key ) {
				if ( isset( $this->metadata[ $key ] ) && is_array( $this->metadata[ $key ] ) ) {
					return array_values( array_map( 'strval', $this->metadata[ $key ] ) );
				}
			}

			return array();
		}

		$keys = CalendarQuery::TYPE_NATION === $type ? self::NATIONAL_KEYS : self::DIOCESAN_KEYS;

		foreach ( $keys as $key ) {
			if ( ! isset( $this->metadata[ $key ] ) || ! is_array( $this->metadata[ $key ] ) ) {
				continue;
			}

			foreach ( $this->metadata[ $key ] as $entry ) {
				if ( is_array( $entry ) && ( $entry['calendar_id'] ?? null ) === $calendar_id ) {
					return isset( $entry['locales'] ) && is_array( $entry['locales'] )
						? array_values( array_map( 'strval', $entry['locales'] ) )
						: array();
				}
			}
		}

		return array();
	}

	/**
	 * Extract calendar ids from a metadata collection, whichever of the two
	 * tolerated shapes it is in.
	 *
	 * @param array<int,mixed> $collection Either a flat list of id strings, or a
	 *                                     list of objects carrying a `calendar_id` property.
	 * @return string[]
	 */
	private function pluck_ids( array $collection ): array {
		$ids = array();

		foreach ( $collection as $entry ) {
			if ( is_string( $entry ) ) {
				$ids[] = $entry;
			} elseif ( is_array( $entry ) && isset( $entry['calendar_id'] ) ) {
				$ids[] = (string) $entry['calendar_id'];
			}
		}

		return $ids;
	}
}
