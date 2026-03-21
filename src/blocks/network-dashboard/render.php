<?php
/**
 * Render Network Dashboard block.
 *
 * Displays network-wide analytics for platform administrators including
 * group health metrics, pending applications, and dormancy alerts.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Group;
use GatherPress\Core\Group_Application;
use GatherPress\Core\Dormancy_Detector;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Only show to super admins.
if ( ! is_super_admin() ) {
	return;
}

// Gather network-wide metrics.
$gatherpress_all_groups  = Group::get_all_groups( array( 'number' => 500 ) );
$gatherpress_total_groups = count( $gatherpress_all_groups );

// Count members across all groups.
$gatherpress_total_members = 0;
$gatherpress_dormant_groups = array();
$gatherpress_at_risk_groups = array();

foreach ( $gatherpress_all_groups as $gatherpress_group ) {
	$gatherpress_total_members += $gatherpress_group->get_member_count();

	$gatherpress_status = $gatherpress_group->get_status();
	if ( 'dormant' === $gatherpress_status ) {
		$gatherpress_dormant_groups[] = $gatherpress_group;
	}
}

// Count pending applications.
$gatherpress_pending_count = 0;
if ( is_main_site() ) {
	$gatherpress_pending_query = new WP_Query(
		array(
			'post_type'      => Group_Application::POST_TYPE,
			'post_status'    => Group_Application::STATUS_PENDING,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$gatherpress_pending_count = $gatherpress_pending_query->found_posts;
}

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-network-dashboard' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<h2 class="wp-block-gatherpress-network-dashboard__title">
		<?php esc_html_e( 'Network Dashboard', 'gatherpress' ); ?>
	</h2>

	<!-- Network Metrics -->
	<div class="wp-block-gatherpress-network-dashboard__metrics">
		<div class="wp-block-gatherpress-network-dashboard__metric-card">
			<span class="wp-block-gatherpress-network-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_total_groups ); ?></span>
			<span class="wp-block-gatherpress-network-dashboard__metric-label"><?php esc_html_e( 'Active Groups', 'gatherpress' ); ?></span>
		</div>
		<div class="wp-block-gatherpress-network-dashboard__metric-card">
			<span class="wp-block-gatherpress-network-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_total_members ); ?></span>
			<span class="wp-block-gatherpress-network-dashboard__metric-label"><?php esc_html_e( 'Total Members', 'gatherpress' ); ?></span>
		</div>
		<div class="wp-block-gatherpress-network-dashboard__metric-card wp-block-gatherpress-network-dashboard__metric-card--alert">
			<span class="wp-block-gatherpress-network-dashboard__metric-value"><?php echo esc_html( (string) $gatherpress_pending_count ); ?></span>
			<span class="wp-block-gatherpress-network-dashboard__metric-label"><?php esc_html_e( 'Pending Applications', 'gatherpress' ); ?></span>
		</div>
		<div class="wp-block-gatherpress-network-dashboard__metric-card wp-block-gatherpress-network-dashboard__metric-card--warning">
			<span class="wp-block-gatherpress-network-dashboard__metric-value"><?php echo esc_html( (string) count( $gatherpress_dormant_groups ) ); ?></span>
			<span class="wp-block-gatherpress-network-dashboard__metric-label"><?php esc_html_e( 'Dormant Groups', 'gatherpress' ); ?></span>
		</div>
	</div>

	<!-- Dormant Groups -->
	<?php if ( ! empty( $gatherpress_dormant_groups ) ) : ?>
		<div class="wp-block-gatherpress-network-dashboard__section">
			<h3><?php esc_html_e( 'Dormant Groups', 'gatherpress' ); ?></h3>
			<ul class="wp-block-gatherpress-network-dashboard__list">
				<?php foreach ( $gatherpress_dormant_groups as $gatherpress_dormant ) : ?>
					<li>
						<a href="<?php echo esc_url( $gatherpress_dormant->get_url() ); ?>">
							<?php echo esc_html( $gatherpress_dormant->get_name() ); ?>
						</a>
						<span class="wp-block-gatherpress-network-dashboard__badge wp-block-gatherpress-network-dashboard__badge--dormant">
							<?php esc_html_e( 'Dormant', 'gatherpress' ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Quick Actions -->
	<div class="wp-block-gatherpress-network-dashboard__section">
		<h3><?php esc_html_e( 'Quick Actions', 'gatherpress' ); ?></h3>
		<div class="wp-block-gatherpress-network-dashboard__action-links">
			<?php if ( is_main_site() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=gatherpress_event&page=gatherpress-meetup-import' ) ); ?>" class="wp-block-gatherpress-network-dashboard__action-link">
					<?php esc_html_e( 'Import from Meetup', 'gatherpress' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( network_admin_url( 'sites.php' ) ); ?>" class="wp-block-gatherpress-network-dashboard__action-link">
				<?php esc_html_e( 'Manage Sites', 'gatherpress' ); ?>
			</a>
			<a href="<?php echo esc_url( network_admin_url( 'users.php' ) ); ?>" class="wp-block-gatherpress-network-dashboard__action-link">
				<?php esc_html_e( 'Manage Users', 'gatherpress' ); ?>
			</a>
		</div>
	</div>

</div>
