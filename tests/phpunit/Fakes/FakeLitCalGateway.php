<?php
/**
 * Fake LitCal gateway.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Tests\Fakes;

use Kalenda\Api\CalendarQuery;
use Kalenda\Contracts\LitCalGateway;
use Throwable;

/**
 * A configurable {@see LitCalGateway} double.
 *
 * Returns fixed data or throws a given exception, and counts calendar() calls
 * so tests can assert a rejected request never reaches the "upstream".
 */
final class FakeLitCalGateway implements LitCalGateway {

	/**
	 * Number of times {@see calendar()} has been called.
	 */
	public int $calendar_calls = 0;

	/**
	 * @param array<string,mixed> $metadata           Value metadata() returns.
	 * @param array<string,mixed> $calendar_data      Value calendar() returns.
	 * @param Throwable|null      $metadata_exception Thrown by metadata() instead of returning, if set.
	 * @param Throwable|null      $calendar_exception Thrown by calendar() instead of returning, if set.
	 */
	public function __construct(
		private array $metadata = array(),
		private array $calendar_data = array(),
		private ?Throwable $metadata_exception = null,
		private ?Throwable $calendar_exception = null
	) {}

	public function calendar( CalendarQuery $query ): array {
		++$this->calendar_calls;

		if ( null !== $this->calendar_exception ) {
			throw $this->calendar_exception;
		}

		return $this->calendar_data;
	}

	public function metadata(): array {
		if ( null !== $this->metadata_exception ) {
			throw $this->metadata_exception;
		}

		return $this->metadata;
	}
}
