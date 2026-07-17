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

$date = current_datetime();

$repository  = kalenda()->calendar_repository();
$day_service = kalenda()->day_service();

try {
	$query = CalendarQuery::create(
		(string) ( $attributes['type'] ?? 'general' ),
		(string) ( $attributes['calendarId'] ?? '' ),
		(int) $date->format( 'Y' ),
		CalendarQuery::YEAR_CIVIL,
		(string) ( $attributes['locale'] ?? 'en' )
	);
} catch ( InvalidArgumentException $e ) {
	$kalenda_render_error( __( 'This block is not configured correctly.', 'kalenda' ) );
	return;
}

$data = $repository->fetch( $query );

if ( $data instanceof WP_Error ) {
	$kalenda_render_error( __( "Unable to load today's celebrations.", 'kalenda' ) );
	return;
}

$events = $day_service->filter( (array) ( $data['litcal'] ?? array() ), $date );

$today_label = wp_date( get_option( 'date_format' ), $date->getTimestamp() );

$title     = trim( (string) ( $attributes['title'] ?? '' ) );
$show_date = (bool) ( $attributes['showDate'] ?? true );

if ( '' === $title ) {
	$title = __( 'Today', 'kalenda' );
}

$style = sanitize_key( $attributes['style'] ?? 'default' );

$allowed_styles = array(
	'default',
	'minimal',
);

if ( ! in_array( $style, $allowed_styles, true ) ) {
	$style = 'default';
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'kalenda-day--' . $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<div class="kalenda-day__header">
	<h2 class="kalenda-day__title">
		<?php echo esc_html( $title ); ?>
	</h2>
	<?php if ( $show_date ) : ?>
		<p class="kalenda-day__date">
			<?php echo esc_html( $today_label ); ?>
		</p>
	<?php endif; ?>
	</div>

	<?php if ( empty( $events ) ) : ?>
		<p class="kalenda-day__empty">
			<?php esc_html_e( 'No celebrations found for today.', 'kalenda' ); ?>
		</p>
	<?php else : ?>
		<div class="kalenda-day__events">
			<ul class="kalenda-day__events-list">
				<?php foreach ( $events as $event ) : ?>
					<li class="kalenda-day__event">
						<?php
						$meta = array();

						if ( ! empty( $event['grade_lcl'] ) ) {
							$meta[] = (string) $event['grade_lcl'];
						}

						if ( ! empty( $event['liturgical_season_lcl'] ) ) {
							$meta[] = (string) $event['liturgical_season_lcl'];
						}
						?>
						<h3 class="kalenda-day__name event-color-<?php echo esc_attr( (string) ( $event['color'][0] ?? 'white' ) ); ?>">
							<?php echo esc_html( (string) ( $event['name'] ?? '' ) ); ?>
						</h3>

						<?php if ( 'default' === $style && ! empty( $meta ) ) : ?>
							<p class="kalenda-day__meta">
								<?php foreach ( $meta as $item ) : ?>
								<span class="kalenda-day__meta-item">
									<?php echo esc_html( $item ); ?>
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
