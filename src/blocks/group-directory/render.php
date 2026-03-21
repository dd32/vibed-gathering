<?php
/**
 * Render Group Directory block.
 *
 * Displays a searchable, paginated grid of all community groups
 * in the WordPress multisite network.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Group;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_per_page    = $attributes['perPage'] ?? 12;
$gatherpress_show_search = $attributes['showSearch'] ?? true;
$gatherpress_columns     = $attributes['columns'] ?? 3;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gatherpress_current_page = max( 1, absint( $_GET['gp_page'] ?? 1 ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gatherpress_search = sanitize_text_field( wp_unslash( $_GET['gp_search'] ?? '' ) );

$gatherpress_args = array(
	'number' => $gatherpress_per_page,
	'offset' => ( $gatherpress_current_page - 1 ) * $gatherpress_per_page,
);

if ( ! empty( $gatherpress_search ) ) {
	$gatherpress_args['search'] = $gatherpress_search;
}

$gatherpress_groups = Group::get_all_groups( $gatherpress_args );

// Get total count for pagination.
$gatherpress_total_args = array( 'number' => 0 );
if ( ! empty( $gatherpress_search ) ) {
	$gatherpress_total_args['search'] = $gatherpress_search;
}
$gatherpress_all_groups  = Group::get_all_groups( $gatherpress_total_args );
$gatherpress_total       = count( $gatherpress_all_groups );
$gatherpress_total_pages = (int) ceil( $gatherpress_total / $gatherpress_per_page );

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-group-directory' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $gatherpress_show_search ) : ?>
		<div class="wp-block-gatherpress-group-directory__search" role="search" aria-label="<?php esc_attr_e( 'Search groups', 'gatherpress' ); ?>">
			<form method="get" action="">
				<label for="gp-group-search" class="screen-reader-text"><?php esc_html_e( 'Search groups', 'gatherpress' ); ?></label>
				<input type="search" id="gp-group-search" name="gp_search" value="<?php echo esc_attr( $gatherpress_search ); ?>" placeholder="<?php esc_attr_e( 'Search groups...', 'gatherpress' ); ?>" />
				<button type="submit"><?php esc_html_e( 'Search', 'gatherpress' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( empty( $gatherpress_groups ) ) : ?>
		<div class="wp-block-gatherpress-group-directory__empty">
			<?php if ( ! empty( $gatherpress_search ) ) : ?>
				<?php
				printf(
					/* translators: %s: search term. */
					esc_html__( 'No groups found matching "%s".', 'gatherpress' ),
					esc_html( $gatherpress_search )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'No groups found.', 'gatherpress' ); ?>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wp-block-gatherpress-group-directory__grid wp-block-gatherpress-group-directory__grid--cols-<?php echo esc_attr( $gatherpress_columns ); ?>">
			<?php foreach ( $gatherpress_groups as $gatherpress_group ) : ?>
				<?php
				$gatherpress_location = $gatherpress_group->get_location();
				$gatherpress_loc_str  = array_filter( array( $gatherpress_location['city'], $gatherpress_location['country'] ) );
				$gatherpress_members  = $gatherpress_group->get_member_count();
				$gatherpress_type     = $gatherpress_group->get_type();
				$gatherpress_upcoming = $gatherpress_group->get_upcoming_events( 1 );
				$gatherpress_url      = $gatherpress_group->get_url();
				$gatherpress_name     = $gatherpress_group->get_name();
				$gatherpress_initial  = mb_strtoupper( mb_substr( $gatherpress_name, 0, 1 ) );

				$gatherpress_type_labels = array(
					'in-person' => __( 'In-Person', 'gatherpress' ),
					'online'    => __( 'Online', 'gatherpress' ),
					'hybrid'    => __( 'Hybrid', 'gatherpress' ),
				);
				$gatherpress_type_label  = $gatherpress_type_labels[ $gatherpress_type ] ?? $gatherpress_type;
				?>
				<div class="wp-block-gatherpress-group-directory__card">
					<a href="<?php echo esc_url( $gatherpress_url ); ?>">
						<div class="wp-block-gatherpress-group-directory__card-image-placeholder">
							<?php echo esc_html( $gatherpress_initial ); ?>
						</div>
						<div class="wp-block-gatherpress-group-directory__card-body">
							<h3 class="wp-block-gatherpress-group-directory__card-title"><?php echo esc_html( $gatherpress_name ); ?></h3>
							<div class="wp-block-gatherpress-group-directory__card-meta">
								<?php if ( ! empty( $gatherpress_loc_str ) ) : ?>
									<span class="wp-block-gatherpress-group-directory__card-location">
										&#128205; <?php echo esc_html( implode( ', ', $gatherpress_loc_str ) ); ?>
									</span>
								<?php endif; ?>
								<span class="wp-block-gatherpress-group-directory__card-members">
									&#128101;
									<?php
									printf(
										/* translators: %d: number of members. */
										esc_html( _n( '%d member', '%d members', $gatherpress_members, 'gatherpress' ) ),
										esc_html( $gatherpress_members )
									);
									?>
								</span>
								<?php if ( ! empty( $gatherpress_upcoming ) ) : ?>
									<span class="wp-block-gatherpress-group-directory__card-next-event">
										&#128197;
										<?php
										printf(
											/* translators: %s: event title. */
											esc_html__( 'Next: %s', 'gatherpress' ),
											esc_html( $gatherpress_upcoming[0]['title'] ?? '' )
										);
										?>
									</span>
								<?php endif; ?>
							</div>
							<span class="wp-block-gatherpress-group-directory__card-type"><?php echo esc_html( $gatherpress_type_label ); ?></span>
						</div>
					</a>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $gatherpress_total_pages > 1 ) : ?>
			<nav class="wp-block-gatherpress-group-directory__pagination" aria-label="<?php esc_attr_e( 'Group directory pagination', 'gatherpress' ); ?>">
				<?php
				for ( $gatherpress_i = 1; $gatherpress_i <= $gatherpress_total_pages; $gatherpress_i++ ) {
					$gatherpress_page_url = add_query_arg( array( 'gp_page' => $gatherpress_i, 'gp_search' => $gatherpress_search ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

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
