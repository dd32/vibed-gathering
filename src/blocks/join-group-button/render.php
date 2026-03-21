<?php
/**
 * Render Join Group Button block.
 *
 * Displays a join/leave group button depending on the user's membership status.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Group;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_group   = new Group();
$gatherpress_blog_id = $gatherpress_group->get_blog_id();
$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-join-group-button' )
);

// Don't show on the main site.
if ( is_main_site() ) {
	return;
}

$gatherpress_is_logged_in = is_user_logged_in();
$gatherpress_is_member    = $gatherpress_is_logged_in && $gatherpress_group->is_member( get_current_user_id() );
$gatherpress_member_count = $gatherpress_group->get_member_count();
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-blog-id="<?php echo esc_attr( (string) $gatherpress_blog_id ); ?>" data-i18n-joining="<?php esc_attr_e( 'Joining...', 'gatherpress' ); ?>" data-i18n-leaving="<?php esc_attr_e( 'Leaving...', 'gatherpress' ); ?>" data-i18n-confirm-leave="<?php esc_attr_e( 'Are you sure you want to leave this group?', 'gatherpress' ); ?>" data-i18n-error="<?php esc_attr_e( 'Something went wrong.', 'gatherpress' ); ?>">
	<?php if ( ! $gatherpress_is_logged_in ) : ?>
		<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="wp-block-gatherpress-join-group-button__btn wp-block-gatherpress-join-group-button__btn--login">
			<?php esc_html_e( 'Log in to Join', 'gatherpress' ); ?>
		</a>
	<?php elseif ( $gatherpress_is_member ) : ?>
		<button class="wp-block-gatherpress-join-group-button__btn wp-block-gatherpress-join-group-button__btn--leave" data-action="leave">
			<?php esc_html_e( 'Leave Group', 'gatherpress' ); ?>
		</button>
	<?php else : ?>
		<button class="wp-block-gatherpress-join-group-button__btn wp-block-gatherpress-join-group-button__btn--join" data-action="join">
			<?php esc_html_e( 'Join Group', 'gatherpress' ); ?>
		</button>
	<?php endif; ?>
	<span class="wp-block-gatherpress-join-group-button__count" aria-live="polite">
		<?php
		printf(
			/* translators: %d: number of members. */
			esc_html( _n( '%d member', '%d members', $gatherpress_member_count, 'gatherpress' ) ),
			esc_html( $gatherpress_member_count )
		);
		?>
	</span>
</div>
