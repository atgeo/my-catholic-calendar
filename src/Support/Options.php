<?php
/**
 * Settings accessor.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Support;

use MyCatholicCalendar\Api\CalendarQuery;

/**
 * Typed, read-only accessor over the plugin's stored settings.
 *
 * A single option (`mcc_settings`) holds all configuration; this class
 * merges it with defaults and exposes each value with a guaranteed type, so
 * callers never deal with raw option arrays or missing keys.
 */
final class Options {

	public const OPTION = 'mcc_settings';

	/**
	 * Resolved settings, merged with defaults.
	 *
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * Merge the given settings over the defaults.
	 *
	 * @param array<string,mixed> $data Raw settings (already merged or not).
	 */
	private function __construct( array $data ) {
		$this->data = array_merge( self::defaults(), $data );
	}

	/**
	 * Load the current settings from the database.
	 *
	 * @return self
	 */
	public static function load(): self {
		$stored = get_option( self::OPTION, array() );

		return new self( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Build from an explicit array (useful in tests).
	 *
	 * @param array<string,mixed> $data Settings.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'calendar_type' => CalendarQuery::TYPE_GENERAL,
			'calendar_id'   => '',
			'locale'        => 'en',
			'year_type'     => CalendarQuery::YEAR_LITURGICAL,
			'cache_ttl'     => 12 * HOUR_IN_SECONDS,
		);
	}

	/**
	 * The default calendar type (general | nation | diocese).
	 *
	 * @return string
	 */
	public function calendar_type(): string {
		return (string) $this->data['calendar_type'];
	}

	/**
	 * The default nation code or diocese id (empty for the general calendar).
	 *
	 * @return string
	 */
	public function calendar_id(): string {
		return (string) $this->data['calendar_id'];
	}

	/**
	 * The default locale.
	 *
	 * @return string
	 */
	public function locale(): string {
		return (string) $this->data['locale'];
	}

	/**
	 * The default year type (LITURGICAL | CIVIL).
	 *
	 * @return string
	 */
	public function year_type(): string {
		return (string) $this->data['year_type'];
	}

	/**
	 * Cache lifetime in seconds for current/future calendar years.
	 *
	 * @return int
	 */
	public function cache_ttl(): int {
		return max( 0, (int) $this->data['cache_ttl'] );
	}

	/**
	 * The full resolved settings array.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		return $this->data;
	}
}
