<?php
/**
 * Minimal WordPress core class doubles for unit tests.
 *
 * Brain Monkey fakes WordPress *functions*, not classes, so tests that
 * construct WP_Error/WP_REST_Request/WP_REST_Response objects need real class
 * definitions. These are slimmed-down stand-ins covering only the behaviour
 * CalendarController relies on (flat param storage, no method/priority
 * merging) — test infrastructure only, never autoloaded or shipped from src/.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var array<string,array<int,string>> */
		private array $errors = array();

		/** @var array<string,mixed> */
		private array $error_data = array();

		public function __construct( string $code = '', string $message = '', mixed $data = null ) {
			if ( '' === $code ) {
				return;
			}

			$this->errors[ $code ][] = $message;

			if ( null !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code(): string {
			$codes = array_keys( $this->errors );

			return $codes[0] ?? '';
		}

		public function get_error_message( string $code = '' ): string {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}

			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_data( string $code = '' ): mixed {
			if ( '' === $code ) {
				$code = $this->get_error_code();
			}

			return $this->error_data[ $code ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const READABLE = 'GET';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * A flat, params-only double.
	 *
	 * Real WP_REST_Request merges URL/GET/POST/JSON/default params by request
	 * method; tests instead construct it with the final resolved values, which
	 * is all CalendarController's `$request['key']` reads ever observe.
	 */
	class WP_REST_Request implements ArrayAccess {
		/** @param array<string,mixed> $params */
		public function __construct(
			private array $params = array()
		) {}

		public function offsetExists( mixed $offset ): bool {
			return isset( $this->params[ $offset ] );
		}

		public function offsetGet( mixed $offset ): mixed {
			return $this->params[ $offset ] ?? null;
		}

		public function offsetSet( mixed $offset, mixed $value ): void {
			$this->params[ $offset ] = $value;
		}

		public function offsetUnset( mixed $offset ): void {
			unset( $this->params[ $offset ] );
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private int $status;

		/** @var array<string,string> */
		private array $headers = array();

		public function __construct(
			private mixed $data = null,
			int $status = 200
		) {
			$this->status = $status;
		}

		public function get_data(): mixed {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}

		public function header( string $key, string $value ): void {
			$this->headers[ $key ] = $value;
		}

		/** @return array<string,string> */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}
