<?php
/**
 * CalendarController tests.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Tests\Rest;

use Brain\Monkey\Functions;
use Kalenda\Exceptions\GatewayException;
use Kalenda\Rest\CalendarController;
use Kalenda\Support\Options;
use Kalenda\Tests\Fakes\FakeLitCalGateway;
use Kalenda\Tests\TestCase;
use WP_Error;
use WP_REST_Request;

/**
 * Covers the three highest-value REST behaviours: rejecting an unknown
 * calendar id, rejecting an impossible `/day` date, and mapping an
 * unavailable upstream to a 502 — without leaking its internal message.
 */
final class CalendarControllerTest extends TestCase {

	public function test_calendar_rejects_unknown_calendar_id_with_400(): void {
		Functions\when( '__' )->returnArg( 1 );

		$gateway = new FakeLitCalGateway(
			metadata: array( 'national_calendars_keys' => array( 'US', 'IT' ) )
		);

		$controller = new CalendarController( $gateway, Options::from_array( array() ) );

		$response = $controller->get_calendar(
			new WP_REST_Request(
				array(
					'type'        => 'nation',
					'calendar_id' => 'ZZ',
					'year'        => 2026,
					'year_type'   => 'LITURGICAL',
					'locale'      => 'en',
				)
			)
		);

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'rest_invalid_param', $response->get_error_code() );
		self::assertSame( 400, $response->get_error_data()['status'] );
		self::assertSame( 0, $gateway->calendar_calls, 'An unknown calendar id must never reach the gateway.' );
	}

	public function test_day_route_rejects_an_impossible_date_with_400(): void {
		Functions\when( 'current_time' )->justReturn( 2026 );

		$captured = array();
		Functions\expect( 'register_rest_route' )
			->twice()
			->andReturnUsing(
				function ( string $namespace, string $route, array $args ) use ( &$captured ): bool {
					$captured[ $route ] = $args;
					return true;
				}
			);

		$controller = new CalendarController( new FakeLitCalGateway(), Options::from_array( array() ) );
		$controller->register_routes();

		self::assertArrayHasKey( '/day', $captured );
		$validate = $captured['/day']['args']['date']['validate_callback'];

		// WP_REST_Server treats a false validate_callback as a rest_invalid_param
		// 400 response, so asserting false here is asserting the 400 outcome.
		self::assertFalse( $validate( '2026-02-30' ), 'February 30 does not exist.' );
		self::assertFalse( $validate( '2026-13-01' ), 'Month 13 does not exist.' );
		self::assertFalse( $validate( 'not-a-date' ), 'A malformed string is rejected.' );
		self::assertTrue( $validate( '2026-06-15' ), 'A real calendar date is accepted.' );
	}

	public function test_calendar_maps_gateway_exception_to_502_without_leaking_its_message(): void {
		Functions\when( '__' )->returnArg( 1 );

		$gateway = new FakeLitCalGateway(
			calendar_exception: new GatewayException( 'boom: upstream connection refused' )
		);

		$controller = new CalendarController( $gateway, Options::from_array( array() ) );

		$response = $controller->get_calendar(
			new WP_REST_Request(
				array(
					'type'        => 'general',
					'calendar_id' => '',
					'year'        => 2026,
					'year_type'   => 'LITURGICAL',
					'locale'      => 'en',
				)
			)
		);

		self::assertInstanceOf( WP_Error::class, $response );
		self::assertSame( 'kalenda_upstream_unavailable', $response->get_error_code() );
		self::assertSame( 502, $response->get_error_data()['status'] );
		self::assertStringNotContainsString( 'boom', $response->get_error_message() );
	}
}
