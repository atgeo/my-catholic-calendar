<?php
/**
 * LitCal gateway implementation.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Api;

use MyCatholicCalendar\Contracts\Cache;
use MyCatholicCalendar\Contracts\LitCalGateway;
use MyCatholicCalendar\Exceptions\GatewayException;
use MyCatholicCalendar\Cache\TransientCache;
use MyCatholicCalendar\Support\Options;
use LiturgicalCalendar\Components\ApiClient;
use LiturgicalCalendar\Components\Http\HttpClientInterface;
use Throwable;

/**
 * Fetches liturgical calendar data from the LitCal API.
 *
 * Uses the official components library to build requests and validate
 * responses, but owns transport (via {@see WpHttpClient}) and caching (via a
 * {@see Cache}) itself, so all HTTP goes through WordPress and all caching
 * lives in one place.
 */
final class LitCalClient implements LitCalGateway {

	/**
	 * Current stable LitCal API base URL.
	 */
	public const DEFAULT_API_URL = 'https://litcal.johnromanodorazio.com/api/v5';

	/**
	 * Cache key for the metadata index.
	 */
	private const METADATA_KEY = 'my_catholic_calendar_metadata';

	/**
	 * Wire the gateway's collaborators.
	 *
	 * @param HttpClientInterface $http        Transport passed to the LitCal library.
	 * @param Cache               $cache       Response cache.
	 * @param string              $api_url     API base URL (no trailing slash required).
	 * @param int                 $default_ttl Cache lifetime for current/future years, in seconds.
	 */
	public function __construct(
		private HttpClientInterface $http,
		private Cache $cache,
		private string $api_url,
		private int $default_ttl
	) {}

	/**
	 * Assemble a client configured for the current WordPress site.
	 *
	 * @param Options $options Plugin settings.
	 * @return self
	 */
	public static function create( Options $options ): self {
		/**
		 * Filter the LitCal API base URL.
		 *
		 * @param string $api_url Default API base URL.
		 */
		$api_url = (string) apply_filters( 'my_catholic_calendar_api_url', self::DEFAULT_API_URL );

		return new self(
			new WpHttpClient(),
			new TransientCache(),
			$api_url,
			$options->cache_ttl()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param CalendarQuery $query The calendar to fetch.
	 * @return array<string,mixed>
	 *
	 * @throws GatewayException When the data cannot be retrieved or is malformed.
	 */
	public function calendar( CalendarQuery $query ): array {
		$key    = $query->cache_key();
		$cached = $this->cache->get( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$request = $this->api_client()
				->calendar()
				->year( $query->year )
				->yearType( $query->year_type )
				->locale( $query->locale );

			if ( CalendarQuery::TYPE_NATION === $query->type ) {
				$request->nation( (string) $query->calendar_id );
			} elseif ( CalendarQuery::TYPE_DIOCESE === $query->type ) {
				$request->diocese( (string) $query->calendar_id );
			}

			$response = $request->get();
		} catch ( Throwable $e ) {
			throw new GatewayException( 'Unable to fetch liturgical calendar: ' . $e->getMessage(), 0, $e );
		}

		$result = $this->to_array( $response );
		$this->cache->set( $key, $result, $this->ttl_for_year( $query->year ) );

		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,mixed>
	 *
	 * @throws GatewayException When the data cannot be retrieved or is malformed.
	 */
	public function metadata(): array {
		$cached = $this->cache->get( self::METADATA_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$response = $this->http->get(
				rtrim( $this->api_url, '/' ) . '/calendars',
				array( 'Accept' => 'application/json' )
			);

			if ( 200 !== $response->getStatusCode() ) {
				throw new GatewayException( 'Metadata request returned status ' . $response->getStatusCode() . '.' );
			}

			$decoded = json_decode( $response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR );
		} catch ( GatewayException $e ) {
			throw $e;
		} catch ( Throwable $e ) {
			throw new GatewayException( 'Unable to fetch calendar metadata: ' . $e->getMessage(), 0, $e );
		}

		if ( ! is_array( $decoded ) ) {
			throw new GatewayException( 'Calendar metadata response was not a JSON object.' );
		}

		$metadata = isset( $decoded['litcal_metadata'] ) && is_array( $decoded['litcal_metadata'] )
			? $decoded['litcal_metadata']
			: $decoded;

		$this->cache->set( self::METADATA_KEY, $metadata, DAY_IN_SECONDS );

		return $metadata;
	}

	/**
	 * Lazily initialise the shared LitCal client with our transport.
	 *
	 * The library's ApiClient is a process-wide singleton; we initialise it with
	 * our HTTP client and base URL on first use. If another plugin bundling the
	 * same library initialised it first, its configuration would win — a
	 * conflict fully resolved later by scoping the vendor namespace at build time.
	 *
	 * @return ApiClient
	 */
	private function api_client(): ApiClient {
		if ( ! ApiClient::isInitialized() ) {
			ApiClient::getInstance(
				array(
					'apiUrl'     => $this->api_url,
					'httpClient' => $this->http,
				)
			);
		}

		return ApiClient::getInstance();
	}

	/**
	 * Choose a cache TTL based on the calendar year.
	 *
	 * Past years are liturgically immutable, so they are cached for a long time;
	 * the current and future years use the configurable default because pending
	 * decrees can still change them.
	 *
	 * @param int $year Calendar year.
	 * @return int TTL in seconds.
	 */
	private function ttl_for_year( int $year ): int {
		$current_year = (int) current_time( 'Y' );

		return $year < $current_year ? YEAR_IN_SECONDS : $this->default_ttl;
	}

	/**
	 * Recursively convert the library's stdClass response into an array.
	 *
	 * @param object $response Decoded LitCal response.
	 * @return array<string,mixed>
	 *
	 * @throws GatewayException When the response cannot be re-encoded.
	 */
	private function to_array( object $response ): array {
		$encoded = wp_json_encode( $response );

		if ( false === $encoded ) {
			throw new GatewayException( 'Failed to normalize the calendar response.' );
		}

		$array = json_decode( $encoded, true );

		return is_array( $array ) ? $array : array();
	}
}
