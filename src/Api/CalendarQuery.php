<?php
/**
 * Calendar query value object.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Api;

use InvalidArgumentException;

/**
 * An immutable description of a single liturgical calendar request.
 *
 * Performs structural validation only (shape, ranges, enums). Verifying that a
 * calendar id actually exists is the caller's responsibility, since that
 * requires the live metadata allowlist.
 */
final class CalendarQuery {

	public const TYPE_GENERAL = 'general';
	public const TYPE_NATION  = 'nation';
	public const TYPE_DIOCESE = 'diocese';

	public const YEAR_LITURGICAL = 'LITURGICAL';
	public const YEAR_CIVIL      = 'CIVIL';

	private const MIN_YEAR = 1970;
	private const MAX_YEAR = 9999;

	/**
	 * Hold the validated query values.
	 *
	 * @param string      $type        One of the TYPE_* constants.
	 * @param string|null $calendar_id Nation code or diocese id; null for the general calendar.
	 * @param int         $year        Gregorian year (1970-9999).
	 * @param string      $year_type   One of the YEAR_* constants.
	 * @param string      $locale      A locale code accepted by the API (e.g. "en" or "en_US").
	 */
	private function __construct(
		public readonly string $type,
		public readonly ?string $calendar_id,
		public readonly int $year,
		public readonly string $year_type,
		public readonly string $locale
	) {}

	/**
	 * Create and validate a query.
	 *
	 * @param string      $type        One of the TYPE_* constants.
	 * @param string|null $calendar_id Nation code or diocese id; null/empty for the general calendar.
	 * @param int         $year        Gregorian year (1970-9999).
	 * @param string      $year_type   One of the YEAR_* constants.
	 * @param string      $locale      Locale code.
	 * @return self
	 *
	 * @throws InvalidArgumentException When any argument is out of range or inconsistent.
	 */
	public static function create( string $type, ?string $calendar_id, int $year, string $year_type, string $locale ): self {
		if ( ! in_array( $type, array( self::TYPE_GENERAL, self::TYPE_NATION, self::TYPE_DIOCESE ), true ) ) {
			throw new InvalidArgumentException( 'Unknown calendar type: ' . $type );
		}

		$id = ( null === $calendar_id || '' === $calendar_id ) ? null : $calendar_id;

		if ( self::TYPE_GENERAL !== $type && null === $id ) {
			throw new InvalidArgumentException( 'A calendar id is required for ' . $type . ' calendars.' );
		}

		if ( $year < self::MIN_YEAR || $year > self::MAX_YEAR ) {
			throw new InvalidArgumentException(
				sprintf( 'Year must be between %d and %d.', self::MIN_YEAR, self::MAX_YEAR )
			);
		}

		$normalized_year_type = strtoupper( $year_type );
		if ( ! in_array( $normalized_year_type, array( self::YEAR_LITURGICAL, self::YEAR_CIVIL ), true ) ) {
			throw new InvalidArgumentException( 'Unknown year type: ' . $year_type );
		}

		if ( '' === trim( $locale ) ) {
			throw new InvalidArgumentException( 'Locale must not be empty.' );
		}

		return new self( $type, $id, $year, $normalized_year_type, $locale );
	}

	/**
	 * Build a query from a loosely-typed associative array (e.g. REST params).
	 *
	 * @param array<string,mixed> $data Keys: type, calendar_id, year, year_type, locale.
	 * @return self
	 *
	 * @throws InvalidArgumentException When required data is missing or invalid.
	 */
	public static function from_array( array $data ): self {
		return self::create(
			isset( $data['type'] ) ? (string) $data['type'] : self::TYPE_GENERAL,
			isset( $data['calendar_id'] ) ? (string) $data['calendar_id'] : null,
			isset( $data['year'] ) ? (int) $data['year'] : 0,
			isset( $data['year_type'] ) ? (string) $data['year_type'] : self::YEAR_LITURGICAL,
			isset( $data['locale'] ) ? (string) $data['locale'] : ''
		);
	}

	/**
	 * A stable cache key uniquely identifying this query.
	 *
	 * @return string
	 */
	public function cache_key(): string {
		return 'mcc_cal_' . md5(
			implode(
				'|',
				array(
					$this->type,
					(string) $this->calendar_id,
					(string) $this->year,
					$this->year_type,
					$this->locale,
				)
			)
		);
	}
}
