<?php
/**
 * WordPress HTTP client adapter.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

namespace MyCatholicCalendar\Api;

use LiturgicalCalendar\Components\Http\HttpClientInterface;
use LiturgicalCalendar\Components\Http\HttpException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Routes the LitCal library's HTTP calls through the WordPress HTTP API.
 *
 * Implementing the library's own {@see HttpClientInterface} (rather than
 * letting it build a client) guarantees every outbound request honours the
 * site's configured transports, proxies and filters, and keeps the plugin
 * within the WordPress.org guideline of using the HTTP API for remote calls.
 */
final class WpHttpClient implements HttpClientInterface {

	/**
	 * Create the client.
	 *
	 * @param int $timeout Request timeout in seconds.
	 */
	public function __construct(
		private int $timeout = 15
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $url     URL to fetch.
	 * @param array<string,string> $headers Request headers.
	 * @return ResponseInterface
	 *
	 * @throws HttpException When the request fails at the transport level.
	 */
	public function get( string $url, array $headers = array() ): ResponseInterface {
		return $this->request( 'GET', $url, null, $headers );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string                     $url     URL to post to.
	 * @param array<string,mixed>|string $body    Request body (arrays are JSON encoded).
	 * @param array<string,string>       $headers Request headers.
	 * @return ResponseInterface
	 *
	 * @throws HttpException When the body cannot be encoded or the request fails.
	 */
	public function post( string $url, array|string $body, array $headers = array() ): ResponseInterface {
		if ( is_array( $body ) ) {
			$encoded = wp_json_encode( $body );
			if ( false === $encoded ) {
				throw new HttpException( 'Failed to encode request body as JSON.' );
			}
			if ( ! $this->has_header( $headers, 'Content-Type' ) ) {
				$headers['Content-Type'] = 'application/json';
			}
			$body = $encoded;
		}

		return $this->request( 'POST', $url, $body, $headers );
	}

	/**
	 * Perform the request and translate the result into a PSR-7 response.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     Target URL.
	 * @param string|null          $body    Raw request body.
	 * @param array<string,string> $headers Request headers.
	 * @return ResponseInterface
	 *
	 * @throws HttpException When WordPress reports a transport error.
	 */
	private function request( string $method, string $url, ?string $body, array $headers ): ResponseInterface {
		$args = array(
			'method'     => $method,
			'timeout'    => $this->timeout,
			'headers'    => $headers,
			'user-agent' => 'MyCatholicCalendar/' . \MyCatholicCalendar\MY_CATHOLIC_CALENDAR_VERSION . '; ' . home_url( '/' ),
		);

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new HttpException(
				sprintf( 'HTTP request to %s failed: %s', $url, $response->get_error_message() )
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$content = (string) wp_remote_retrieve_body( $response );

		return new Response( $status, $this->normalize_headers( wp_remote_retrieve_headers( $response ) ), $content );
	}

	/**
	 * Case-insensitively check whether a header is already set.
	 *
	 * @param array<string,string> $headers Header map.
	 * @param string               $name    Header name to look for.
	 * @return bool
	 */
	private function has_header( array $headers, string $name ): bool {
		foreach ( array_keys( $headers ) as $key ) {
			if ( 0 === strcasecmp( (string) $key, $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Coerce WordPress's response headers into a plain array for PSR-7.
	 *
	 * @param mixed $headers Value returned by wp_remote_retrieve_headers().
	 * @return array<string,string|string[]>
	 */
	private function normalize_headers( mixed $headers ): array {
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			return $headers->getAll();
		}

		if ( is_array( $headers ) ) {
			return $headers;
		}

		if ( is_iterable( $headers ) ) {
			$normalized = array();
			foreach ( $headers as $key => $value ) {
				$normalized[ (string) $key ] = $value;
			}
			return $normalized;
		}

		return array();
	}
}
