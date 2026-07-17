<?php
/**
 * Server-side render for the `day` block.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Kalenda\Api\CalendarQuery;
use function Kalenda\kalenda;

/**
 * Render a friendly error and stop, instead of a fatal or a raw WP_Error dump.
 *
 * @param string $message Already-translated, human-readable message.
 */
$kalenda_render_error = static function ( string $message ): void {
	printf(
		'<p class="kalenda-day__error">%s</p>',
		esc_html( $message )
	);
};

$kalenda_date = current_datetime();

$kalenda_repository  = kalenda()->calendar_repository();
$kalenda_day_service = kalenda()->day_service();

try {
	$kalenda_query = CalendarQuery::create(
		(string) ( $attributes['type'] ?? 'general' ),
		(string) ( $attributes['calendarId'] ?? '' ),
		(int) $kalenda_date->format( 'Y' ),
		CalendarQuery::YEAR_CIVIL,
		(string) ( $attributes['locale'] ?? 'en' )
	);
} catch ( InvalidArgumentException $e ) {
	$kalenda_render_error( __( 'This block is not configured correctly.', 'kalenda' ) );
	return;
}

$kalenda_data = $kalenda_repository->fetch( $kalenda_query );

if ( $kalenda_data instanceof WP_Error ) {
	$kalenda_render_error( __( "Unable to load today's celebrations.", 'kalenda' ) );
	return;
}

$kalenda_events = $kalenda_day_service->filter( (array) ( $kalenda_data['litcal'] ?? array() ), $kalenda_date );

$kalenda_today_label = wp_date( get_option( 'date_format' ), $kalenda_date->getTimestamp() );

$kalenda_title     = sanitize_text_field( (string) ( $attributes['title'] ?? '' ) );
$kalenda_show_date = (bool) ( $attributes['showDate'] ?? true );

if ( '' === $kalenda_title ) {
	$kalenda_title = __( 'Today', 'kalenda' );
}

$kalenda_style = sanitize_key( $attributes['style'] ?? 'default' );

$kalenda_allowed_styles = array(
	'default',
	'minimal',
);

if ( ! in_array( $kalenda_style, $kalenda_allowed_styles, true ) ) {
	$kalenda_style = 'default';
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'kalenda-day--' . $kalenda_style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<div class="kalenda-day__header">
	<h2 class="kalenda-day__title">
		<?php echo esc_html( $kalenda_title ); ?>
	</h2>
	<?php if ( $kalenda_show_date ) : ?>
		<p class="kalenda-day__date">
			<?php echo esc_html( $kalenda_today_label ); ?>
		</p>
	<?php endif; ?>
	</div>

	<?php if ( empty( $kalenda_events ) ) : ?>
		<p class="kalenda-day__empty">
			<?php esc_html_e( 'No celebrations found for today.', 'kalenda' ); ?>
		</p>
	<?php else : ?>
		<div class="kalenda-day__events">
			<ul class="kalenda-day__events-list">
				<?php foreach ( $kalenda_events as $kalenda_event ) : ?>
					<li class="kalenda-day__event">
						<?php
						$kalenda_meta_items = array();

						if ( ! empty( $kalenda_event['grade_lcl'] ) ) {
							$kalenda_meta_items[] = (string) $kalenda_event['grade_lcl'];
						}

						if ( ! empty( $kalenda_event['liturgical_season_lcl'] ) ) {
							$kalenda_meta_items[] = (string) $kalenda_event['liturgical_season_lcl'];
						}
						?>
						<h3 class="kalenda-day__name event-color-<?php echo esc_attr( (string) ( $kalenda_event['color'][0] ?? 'white' ) ); ?>">
							<?php echo esc_html( (string) ( $kalenda_event['name'] ?? '' ) ); ?>
						</h3>

						<?php if ( 'default' === $kalenda_style && ! empty( $kalenda_meta_items ) ) : ?>
							<p class="kalenda-day__meta">
								<?php foreach ( $kalenda_meta_items as $kalenda_meta_item ) : ?>
								<span class="kalenda-day__meta-item">
									<?php echo esc_html( $kalenda_meta_item ); ?>
								</span>
								<?php endforeach; ?>
							</p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
