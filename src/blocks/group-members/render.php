<?php
/**
 * Render Group Members block.
 *
 * Displays a grid of group members with avatars and role badges.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Group;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_limit     = $attributes['limit'] ?? 24;
$gatherpress_show_role = $attributes['showRole'] ?? true;

$gatherpress_group   = new Group();
$gatherpress_members = $gatherpress_group->get_members( '', $gatherpress_limit );

$gatherpress_role_labels = array(
	Group::ROLE_ORGANIZER    => __( 'Organizer', 'gatherpress' ),
	Group::ROLE_CO_ORGANIZER => __( 'Co-Organizer', 'gatherpress' ),
	Group::ROLE_MEMBER       => __( 'Member', 'gatherpress' ),
);

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-group-members' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( empty( $gatherpress_members ) ) : ?>
		<p class="wp-block-gatherpress-group-members__empty">
			<?php esc_html_e( 'No members yet.', 'gatherpress' ); ?>
		</p>
	<?php else : ?>
		<div class="wp-block-gatherpress-group-members__grid">
			<?php foreach ( $gatherpress_members as $gatherpress_member ) : ?>
				<?php
				$gatherpress_role  = $gatherpress_group->get_member_role( $gatherpress_member->ID );
				$gatherpress_label = $gatherpress_role_labels[ $gatherpress_role ] ?? '';
				?>
				<div class="wp-block-gatherpress-group-members__member">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar returns safe HTML.
					echo get_avatar( $gatherpress_member->ID, 64, '', $gatherpress_member->display_name );
					?>
					<span class="wp-block-gatherpress-group-members__name">
						<?php echo esc_html( $gatherpress_member->display_name ); ?>
					</span>
					<?php if ( $gatherpress_show_role && ! empty( $gatherpress_label ) && Group::ROLE_MEMBER !== $gatherpress_role ) : ?>
						<span class="wp-block-gatherpress-group-members__role wp-block-gatherpress-group-members__role--<?php echo esc_attr( $gatherpress_role ); ?>">
							<?php echo esc_html( $gatherpress_label ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
