<?php
/**
 * Plugin container and bootstrap.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda;

use Kalenda\Api\LitCalClient;
use Kalenda\Contracts\Registrable;
use Kalenda\Rest\CalendarController;
use Kalenda\Rest\MetadataController;
use Kalenda\Rest\RestRegistrar;
use Kalenda\Support\Options;

/**
 * Central plugin container.
 *
 * Responsible for wiring the plugin's services together and registering them
 * with WordPress. Kept intentionally small: each concern (REST, admin, blocks)
 * lives in its own {@see Registrable} service that is instantiated here.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Retrieve the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use {@see instance()}.
	 */
	private function __construct() {}

	/**
	 * Boot the plugin: load translations and register every service.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		foreach ( $this->services() as $service ) {
			$service->register();
		}
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'kalenda',
			false,
			dirname( plugin_basename( KALENDA_FILE ) ) . '/languages'
		);
	}

	/**
	 * Build the list of services to register.
	 *
	 * Later phases append their services here (REST controller, settings page,
	 * block registrar, shortcode). The `kalenda_services` filter lets add-ons
	 * extend the plugin without editing core.
	 *
	 * @return Registrable[]
	 */
	private function services(): array {
		$options = Options::load();
		$gateway = LitCalClient::create( $options );

		$services = array(
			new RestRegistrar(
				new MetadataController( $gateway ),
				new CalendarController( $gateway, $options )
			),
		);

		/**
		 * Filter the services registered during boot.
		 *
		 * @param Registrable[] $services List of registrable services.
		 */
		$services = (array) apply_filters( 'kalenda_services', $services );

		// A third-party filter callback may return non-conforming values, so the
		// instanceof guard is genuinely needed even though the docblock claims otherwise.
		return array_filter(
			$services,
			static fn ( $service ): bool => $service instanceof Registrable // @phpstan-ignore instanceof.alwaysTrue
		);
	}
}
