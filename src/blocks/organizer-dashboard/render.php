<?php
/**
 * Render Organizer Dashboard block.
 *
 * Displays group analytics, upcoming events, recent RSVPs, and quick
 * action links for organizers. Only visible to organizer/co-organizer roles.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Event;
use GatherPress\Core\Group;
use GatherPress\Core\Newcomer_Tracker;
use GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Only show to logged-in organizers/co-organizers/admins.
if ( ! is_user_logged_in() ) {
	return;
}

$gatherpress_user_id = get_current_user_id();
$gatherpress_group   = new Group();
$gatherpress_role    = $gatherpress_group->get_member_role( $gatherpress_user_id );

if ( ! in_array( $gatherpress_role, array( Group::ROLE_ORGANIZER, Group::ROLE_CO_ORGANIZER ), true ) && ! current_user_can( 'manage_options' ) ) {
	return;
}

// Gather metrics.
$gatherpress_member_count = $gatherpress_group->get_member_count();

// Upcoming events count.
$gatherpress_upcoming_query = new WP_Query(
	array(
		'post_type'      => Event::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'gatherpress_datetime_end_gmt',
				'value'   => gmdate( Event::DATETIME_FORMAT ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
	)
);
$gatherpress_upcoming_count = $gatherpress_upcoming_query->found_posts;

// Next 5 upcoming events.
$gatherpress_next_query = new WP_Query(
	array(
		'post_type'      => Event::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'gatherpress_datetime_end_gmt',
				'value'   => gmdate( Event::DATETIME_FORMAT ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
		'meta_key'       => 'gatherpress_datetime_start_gmt', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
	)
);

// Total events metric.
$gatherpress_past_query = new WP_Query(
	array(
		'post_type'      => Event::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
$gatherpress_total_events = $gatherpress_past_query->found_posts;

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-organizer-dashboard' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<h2 class="wp-block-gatherpress-organizer-dashboard__title">
		<?php esc_html_e( 'Organizer Dashboard', 'gatherpress' ); ?>
	</h2>

	<!-- Metrics Cards -->
	<div class="wp-block-gatherpress-organizer-dashboard__metrics">
		<div class="wp-block-gatherpress-organizer-dashboard__metric-card">
			<span class="wp-block-gatherpress-organizer-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_member_count ); ?></span>
			<span class="wp-block-gatherpress-organizer-dashboard__metric-label"><?php esc_html_e( 'Members', 'gatherpress' ); ?></span>
		</div>
		<div class="wp-block-gatherpress-organizer-dashboard__metric-card">
			<span class="wp-block-gatherpress-organizer-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_upcoming_count ); ?></span>
			<span class="wp-block-gatherpress-organizer-dashboard__metric-label"><?php esc_html_e( 'Upcoming Events', 'gatherpress' ); ?></span>
		</div>
		<div class="wp-block-gatherpress-organizer-dashboard__metric-card">
			<span class="wp-block-gatherpress-organizer-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_total_events ); ?></span>
			<span class="wp-block-gatherpress-organizer-dashboard__metric-label"><?php esc_html_e( 'Total Events', 'gatherpress' ); ?></span>
		</div>
	</div>

	<!-- Quick Actions -->
	<div class="wp-block-gatherpress-organizer-dashboard__actions">
		<h3><?php esc_html_e( 'Quick Actions', 'gatherpress' ); ?></h3>
		<div class="wp-block-gatherpress-organizer-dashboard__action-links">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Event::POST_TYPE ) ); ?>" class="wp-block-gatherpress-organizer-dashboard__action-link">
				<?php esc_html_e( 'Create Event', 'gatherpress' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="wp-block-gatherpress-organizer-dashboard__action-link">
				<?php esc_html_e( 'Manage Members', 'gatherpress' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Event::POST_TYPE ) ); ?>" class="wp-block-gatherpress-organizer-dashboard__action-link">
				<?php esc_html_e( 'All Events', 'gatherpress' ); ?>
			</a>
		</div>
	</div>

	<!-- Upcoming Events List -->
	<?php if ( $gatherpress_next_query->have_posts() ) : ?>
		<div class="wp-block-gatherpress-organizer-dashboard__upcoming">
			<h3><?php esc_html_e( 'Upcoming Events', 'gatherpress' ); ?></h3>
			<ul class="wp-block-gatherpress-organizer-dashboard__event-list">
				<?php
				while ( $gatherpress_next_query->have_posts() ) {
					$gatherpress_next_query->the_post();
					$gatherpress_event     = new Event( get_the_ID() );
					$gatherpress_rsvp      = new Rsvp( get_the_ID() );
					$gatherpress_responses = $gatherpress_rsvp->responses();
					$gatherpress_attending = $gatherpress_responses['attending']['count'] ?? 0;
					$gatherpress_newcomers = Newcomer_Tracker::get_newcomer_count( get_the_ID() );
					?>
					<li class="wp-block-gatherpress-organizer-dashboard__event-item">
						<a href="<?php the_permalink(); ?>">
							<strong><?php the_title(); ?></strong>
						</a>
						<span class="wp-block-gatherpress-organizer-dashboard__event-meta">
							<?php echo esc_html( $gatherpress_event->get_display_datetime() ); ?>
							&middot;
							<?php
							printf(
								/* translators: %d: number of attendees. */
								esc_html( _n( '%d attendee', '%d attendees', $gatherpress_attending, 'gatherpress' ) ),
								esc_html( $gatherpress_attending )
							);
							if ( $gatherpress_newcomers > 0 ) {
								echo ' &middot; ';
								printf(
									/* translators: %d: number of newcomers. */
									esc_html( _n( '%d newcomer', '%d newcomers', $gatherpress_newcomers, 'gatherpress' ) ),
									esc_html( $gatherpress_newcomers )
								);
							}
							?>
						</span>
					</li>
					<?php
				}
				wp_reset_postdata();
				?>
			</ul>
		</div>
	<?php endif; ?>

</div>
