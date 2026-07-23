<?php
/**
 * Server-side render for the `day` block.
 *
 * @package MyCatholicCalendar
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use MyCatholicCalendar\Api\CalendarQuery;
use function MyCatholicCalendar\my_catholic_calendar;

/**
 * Render a friendly error and stop, instead of a fatal or a raw WP_Error dump.
 *
 * @param string $message Already-translated, human-readable message.
 */
$my_catholic_calendar_render_error = static function ( string $message ): void {
	printf(
		'<p class="kalenda-day__error">%s</p>',
		esc_html( $message )
	);
};

$my_catholic_calendar_date = current_datetime();

$my_catholic_calendar_repository  = my_catholic_calendar()->calendar_repository();
$my_catholic_calendar_day_service = my_catholic_calendar()->day_service();

try {
	$my_catholic_calendar_query = CalendarQuery::create(
		(string) ( $attributes['type'] ?? 'general' ),
		(string) ( $attributes['calendarId'] ?? '' ),
		(int) $my_catholic_calendar_date->format( 'Y' ),
		CalendarQuery::YEAR_CIVIL,
		(string) ( $attributes['locale'] ?? 'en' )
	);
} catch ( InvalidArgumentException $e ) {
	$my_catholic_calendar_render_error( __( 'This block is not configured correctly.', 'my-catholic-calendar' ) );
	return;
}

$my_catholic_calendar_data = $my_catholic_calendar_repository->fetch( $my_catholic_calendar_query );

if ( $my_catholic_calendar_data instanceof WP_Error ) {
	$my_catholic_calendar_render_error( __( "Unable to load today's celebrations.", 'my-catholic-calendar' ) );
	return;
}

$my_catholic_calendar_events = $my_catholic_calendar_day_service->filter( (array) ( $my_catholic_calendar_data['litcal'] ?? array() ), $my_catholic_calendar_date );

$my_catholic_calendar_today_label = wp_date( get_option( 'date_format' ), $my_catholic_calendar_date->getTimestamp() );

$my_catholic_calendar_title     = sanitize_text_field( (string) ( $attributes['title'] ?? '' ) );
$my_catholic_calendar_show_date = (bool) ( $attributes['showDate'] ?? true );

if ( '' === $my_catholic_calendar_title ) {
	$my_catholic_calendar_title = __( 'Today', 'my-catholic-calendar' );
}

$my_catholic_calendar_style = sanitize_key( $attributes['style'] ?? 'default' );

$my_catholic_calendar_allowed_styles = array(
	'default',
	'minimal',
);

if ( ! in_array( $my_catholic_calendar_style, $my_catholic_calendar_allowed_styles, true ) ) {
	$my_catholic_calendar_style = 'default';
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'kalenda-day--' . $my_catholic_calendar_style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<div class="kalenda-day__header">
	<h2 class="kalenda-day__title">
		<?php echo esc_html( $my_catholic_calendar_title ); ?>
	</h2>
	<?php if ( $my_catholic_calendar_show_date ) : ?>
		<p class="kalenda-day__date">
			<?php echo esc_html( $my_catholic_calendar_today_label ); ?>
		</p>
	<?php endif; ?>
	</div>

	<?php if ( empty( $my_catholic_calendar_events ) ) : ?>
		<p class="kalenda-day__empty">
			<?php esc_html_e( 'No celebrations found for today.', 'my-catholic-calendar' ); ?>
		</p>
	<?php else : ?>
		<div class="kalenda-day__events">
			<ul class="kalenda-day__events-list">
				<?php foreach ( $my_catholic_calendar_events as $my_catholic_calendar_event ) : ?>
					<li class="kalenda-day__event">
						<?php
						$my_catholic_calendar_meta_items = array();

						if ( ! empty( $my_catholic_calendar_event['grade_lcl'] ) ) {
							$my_catholic_calendar_meta_items[] = (string) $my_catholic_calendar_event['grade_lcl'];
						}

						if ( ! empty( $my_catholic_calendar_event['liturgical_season_lcl'] ) ) {
							$my_catholic_calendar_meta_items[] = (string) $my_catholic_calendar_event['liturgical_season_lcl'];
						}
						?>
						<h3 class="kalenda-day__name event-color-<?php echo esc_attr( (string) ( $my_catholic_calendar_event['color'][0] ?? 'white' ) ); ?>">
							<?php echo esc_html( (string) ( $my_catholic_calendar_event['name'] ?? '' ) ); ?>
						</h3>

						<?php if ( 'default' === $my_catholic_calendar_style && ! empty( $my_catholic_calendar_meta_items ) ) : ?>
							<p class="kalenda-day__meta">
								<?php foreach ( $my_catholic_calendar_meta_items as $my_catholic_calendar_meta_item ) : ?>
								<span class="kalenda-day__meta-item">
									<?php echo esc_html( $my_catholic_calendar_meta_item ); ?>
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
