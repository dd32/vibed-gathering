<?php
/**
 * Render Event Directory block.
 *
 * Displays a searchable, paginated grid of upcoming events across all
 * group sites in the WordPress multisite network.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Event;
use GatherPress\Core\Event_Status;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_per_page    = $attributes['perPage'] ?? 12;
$gatherpress_show_search = $attributes['showSearch'] ?? true;
$gatherpress_columns     = $attributes['columns'] ?? 3;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gatherpress_current_page = max( 1, absint( $_GET['gp_events_page'] ?? 1 ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gatherpress_search = sanitize_text_field( wp_unslash( $_GET['gp_events_search'] ?? '' ) );

// Collect events from all sites (or just current site if not multisite).
$gatherpress_all_events = array();

if ( is_multisite() ) {
	$gatherpress_sites = get_sites(
		array(
			'number'   => 0,
			'public'   => 1,
			'archived' => 0,
			'deleted'  => 0,
			'fields'   => 'ids',
		)
	);

	foreach ( $gatherpress_sites as $gatherpress_site_id ) {
		$gatherpress_site_id = (int) $gatherpress_site_id;
		switch_to_blog( $gatherpress_site_id );

		if ( post_type_exists( Event::POST_TYPE ) ) {
			$gatherpress_site_events = gatherpress_get_directory_events( $gatherpress_site_id, $gatherpress_search );
			$gatherpress_all_events  = array_merge( $gatherpress_all_events, $gatherpress_site_events );
		}

		restore_current_blog();
	}
} else {
	$gatherpress_all_events = gatherpress_get_directory_events( get_current_blog_id(), $gatherpress_search );
}

// Sort by start date ascending.
usort(
	$gatherpress_all_events,
	static function ( $gatherpress_a, $gatherpress_b ) {
		return strcmp( $gatherpress_a['start_gmt'], $gatherpress_b['start_gmt'] );
	}
);

// Paginate.
$gatherpress_total       = count( $gatherpress_all_events );
$gatherpress_total_pages = (int) ceil( $gatherpress_total / $gatherpress_per_page );
$gatherpress_offset      = ( $gatherpress_current_page - 1 ) * $gatherpress_per_page;
$gatherpress_events      = array_slice( $gatherpress_all_events, $gatherpress_offset, $gatherpress_per_page );

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-event-directory' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $gatherpress_show_search ) : ?>
		<div class="wp-block-gatherpress-event-directory__search" role="search" aria-label="<?php esc_attr_e( 'Search events', 'gatherpress' ); ?>">
			<form method="get" action="">
				<label for="gp-event-search" class="screen-reader-text"><?php esc_html_e( 'Search events', 'gatherpress' ); ?></label>
				<input type="search" id="gp-event-search" name="gp_events_search" value="<?php echo esc_attr( $gatherpress_search ); ?>" placeholder="<?php esc_attr_e( 'Search events...', 'gatherpress' ); ?>" />
				<button type="submit"><?php esc_html_e( 'Search', 'gatherpress' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( empty( $gatherpress_events ) ) : ?>
		<div class="wp-block-gatherpress-event-directory__empty">
			<?php if ( ! empty( $gatherpress_search ) ) : ?>
				<?php
				printf(
					/* translators: %s: search term. */
					esc_html__( 'No events found matching "%s".', 'gatherpress' ),
					esc_html( $gatherpress_search )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'No upcoming events found.', 'gatherpress' ); ?>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wp-block-gatherpress-event-directory__grid wp-block-gatherpress-event-directory__grid--cols-<?php echo esc_attr( $gatherpress_columns ); ?>">
			<?php foreach ( $gatherpress_events as $gatherpress_event_data ) : ?>
				<div class="wp-block-gatherpress-event-directory__card">
					<a href="<?php echo esc_url( $gatherpress_event_data['permalink'] ); ?>">
						<?php if ( ! empty( $gatherpress_event_data['thumbnail'] ) ) : ?>
							<img class="wp-block-gatherpress-event-directory__card-image" src="<?php echo esc_url( $gatherpress_event_data['thumbnail'] ); ?>" alt="<?php echo esc_attr( $gatherpress_event_data['title'] ); ?>" />
						<?php else : ?>
							<div class="wp-block-gatherpress-event-directory__card-date-header">
								<span class="wp-block-gatherpress-event-directory__card-date-month"><?php echo esc_html( $gatherpress_event_data['month'] ); ?></span>
								<span class="wp-block-gatherpress-event-directory__card-date-day"><?php echo esc_html( $gatherpress_event_data['day'] ); ?></span>
							</div>
						<?php endif; ?>
						<div class="wp-block-gatherpress-event-directory__card-body">
							<h3 class="wp-block-gatherpress-event-directory__card-title"><?php echo esc_html( $gatherpress_event_data['title'] ); ?></h3>
							<div class="wp-block-gatherpress-event-directory__card-meta">
								<span class="wp-block-gatherpress-event-directory__card-datetime">&#128197; <?php echo esc_html( $gatherpress_event_data['datetime_display'] ); ?></span>
								<?php if ( ! empty( $gatherpress_event_data['venue'] ) ) : ?>
									<span class="wp-block-gatherpress-event-directory__card-venue">&#128205; <?php echo esc_html( $gatherpress_event_data['venue'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $gatherpress_event_data['group_name'] ) ) : ?>
									<span class="wp-block-gatherpress-event-directory__card-group">&#128101; <?php echo esc_html( $gatherpress_event_data['group_name'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</a>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $gatherpress_total_pages > 1 ) : ?>
			<nav class="wp-block-gatherpress-event-directory__pagination" aria-label="<?php esc_attr_e( 'Event directory pagination', 'gatherpress' ); ?>">
				<?php
				for ( $gatherpress_i = 1; $gatherpress_i <= $gatherpress_total_pages; $gatherpress_i++ ) {
					$gatherpress_page_url = add_query_arg( array( 'gp_events_page' => $gatherpress_i, 'gp_events_search' => $gatherpress_search ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

					if ( $gatherpress_i === $gatherpress_current_page ) {
						printf( '<span class="current">%d</span>', esc_html( $gatherpress_i ) );
					} else {
						printf( '<a href="%s">%d</a>', esc_url( $gatherpress_page_url ), esc_html( $gatherpress_i ) );
					}
				}
				?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
/**
 * Query upcoming events from a specific site for the directory.
 *
 * @param int    $gatherpress_site_id The site ID.
 * @param string $gatherpress_search  Optional search term.
 * @return array Array of event data.
 */
if ( ! function_exists( 'gatherpress_get_directory_events' ) ) :
	// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Doc comment is above the function_exists guard.
	function gatherpress_get_directory_events( int $gatherpress_site_id, string $gatherpress_search = '' ): array {
		$gatherpress_events = array();

		$gatherpress_query_args = array(
			'post_type'      => Event::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'gatherpress_datetime_end_gmt',
					'value'   => gmdate( Event::DATETIME_FORMAT ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'meta_key'       => 'gatherpress_datetime_start_gmt', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'            => 'meta_value',
		'order'              => 'ASC',
		);

		if ( ! empty( $gatherpress_search ) ) {
			$gatherpress_query_args['s'] = $gatherpress_search;
		}

		$gatherpress_query    = new WP_Query( $gatherpress_query_args );
		$gatherpress_blog     = get_blog_details( $gatherpress_site_id );
		$gatherpress_grp_name = $gatherpress_blog ? $gatherpress_blog->blogname : '';

		foreach ( $gatherpress_query->posts as $gatherpress_post ) {
			if ( Event_Status::is_cancelled( $gatherpress_post->ID ) ) {
				continue;
			}

			$gatherpress_event    = new \GatherPress\Core\Event( $gatherpress_post->ID );
			$gatherpress_datetime = $gatherpress_event->get_datetime();
			$gatherpress_venue    = $gatherpress_event->get_venue_information();
			$gatherpress_start    = $gatherpress_datetime['datetime_start_gmt'] ?? '';
			$gatherpress_month    = '';
			$gatherpress_day      = '';

			if ( ! empty( $gatherpress_start ) && '0000-00-00 00:00:00' !== $gatherpress_start ) {
				$gatherpress_dt    = new DateTime( $gatherpress_start, new DateTimeZone( 'UTC' ) );
				$gatherpress_month = $gatherpress_dt->format( 'M' );
				$gatherpress_day   = $gatherpress_dt->format( 'j' );
			}

			$gatherpress_events[] = array(
				'title'            => get_the_title( $gatherpress_post->ID ),
				'permalink'        => get_permalink( $gatherpress_post->ID ),
				'thumbnail'        => get_the_post_thumbnail_url( $gatherpress_post->ID, 'medium' ),
				'datetime_display' => $gatherpress_event->get_display_datetime(),
				'start_gmt'        => $gatherpress_start,
				'month'            => $gatherpress_month,
				'day'              => $gatherpress_day,
				'venue'            => $gatherpress_venue['name'] ?? '',
				'group_name'       => $gatherpress_grp_name,
			);
		}

		return $gatherpress_events;
	}
endif;
