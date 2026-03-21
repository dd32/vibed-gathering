<?php
/**
 * Render Event Feedback block.
 *
 * Shows a star rating form for past events and displays aggregated
 * feedback from attendees. Only visible after the event has ended.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Event_Feedback;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_block_inst = Block::get_instance();
$gatherpress_post_id    = $gatherpress_block_inst->get_post_id( $block->parsed_block );
$gatherpress_event      = new Event( $gatherpress_post_id );

// Only show for past events.
if ( ! $gatherpress_event->event ) {
	return;
}

$gatherpress_datetime = $gatherpress_event->get_datetime();
$gatherpress_end_gmt  = $gatherpress_datetime['datetime_end_gmt'] ?? '';

if ( empty( $gatherpress_end_gmt ) || $gatherpress_end_gmt > gmdate( Event::DATETIME_FORMAT ) ) {
	return;
}

// Get existing feedback.
$gatherpress_avg     = Event_Feedback::get_average_rating( $gatherpress_post_id );
$gatherpress_reviews = get_comments(
	array(
		'post_id' => $gatherpress_post_id,
		'type'    => Event_Feedback::COMMENT_TYPE,
		'status'  => 'approve',
		'number'  => 10,
	)
);

// Check if current user already left feedback.
$gatherpress_user_feedback = false;
if ( is_user_logged_in() ) {
	$gatherpress_existing = get_comments(
		array(
			'post_id' => $gatherpress_post_id,
			'user_id' => get_current_user_id(),
			'type'    => Event_Feedback::COMMENT_TYPE,
			'number'  => 1,
		)
	);
	$gatherpress_user_feedback = ! empty( $gatherpress_existing );
}

$gatherpress_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'wp-block-gatherpress-event-feedback',
		'id'    => 'feedback',
	)
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-event-id="<?php echo esc_attr( (string) $gatherpress_post_id ); ?>">

	<h3 class="wp-block-gatherpress-event-feedback__title">
		<?php esc_html_e( 'Event Feedback', 'gatherpress' ); ?>
	</h3>

	<?php if ( $gatherpress_avg > 0 ) : ?>
		<div class="wp-block-gatherpress-event-feedback__summary">
			<div class="wp-block-gatherpress-event-feedback__stars" aria-label="<?php echo esc_attr( sprintf( '%s out of 5 stars', $gatherpress_avg ) ); ?>">
				<?php
				for ( $gatherpress_i = 1; $gatherpress_i <= 5; $gatherpress_i++ ) {
					$gatherpress_filled = $gatherpress_i <= round( $gatherpress_avg ) ? 'filled' : 'empty';
					echo '<span class="wp-block-gatherpress-event-feedback__star wp-block-gatherpress-event-feedback__star--' . esc_attr( $gatherpress_filled ) . '">&#9733;</span>';
				}
				?>
			</div>
			<span class="wp-block-gatherpress-event-feedback__avg">
				<?php echo esc_html( (string) $gatherpress_avg ); ?>/5
				(<?php echo esc_html( (string) count( $gatherpress_reviews ) ); ?>
				<?php echo esc_html( _n( 'review', 'reviews', count( $gatherpress_reviews ), 'gatherpress' ) ); ?>)
			</span>
		</div>
	<?php endif; ?>

	<!-- Feedback form -->
	<?php if ( is_user_logged_in() && ! $gatherpress_user_feedback ) : ?>
		<form class="wp-block-gatherpress-event-feedback__form" data-gp-feedback-form data-i18n-select-rating="<?php esc_attr_e( 'Please select a star rating.', 'gatherpress' ); ?>" data-i18n-submitting="<?php esc_attr_e( 'Submitting...', 'gatherpress' ); ?>" data-i18n-submit="<?php esc_attr_e( 'Submit Feedback', 'gatherpress' ); ?>" data-i18n-success="<?php esc_attr_e( 'Thank you for your feedback!', 'gatherpress' ); ?>" data-i18n-error="<?php esc_attr_e( 'Something went wrong.', 'gatherpress' ); ?>">
			<?php wp_nonce_field( 'wp_rest', '_wpnonce' ); ?>
			<div class="wp-block-gatherpress-event-feedback__rating-input">
				<label><?php esc_html_e( 'Your Rating', 'gatherpress' ); ?></label>
				<div class="wp-block-gatherpress-event-feedback__star-select" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'gatherpress' ); ?>">
					<?php for ( $gatherpress_i = 1; $gatherpress_i <= 5; $gatherpress_i++ ) : ?>
						<button type="button" class="wp-block-gatherpress-event-feedback__star-btn" data-rating="<?php echo esc_attr( (string) $gatherpress_i ); ?>" aria-label="<?php echo esc_attr( sprintf( '%d stars', $gatherpress_i ) ); ?>">
							&#9733;
						</button>
					<?php endfor; ?>
					<input type="hidden" name="rating" value="" required />
				</div>
			</div>
			<div class="wp-block-gatherpress-event-feedback__comment-input">
				<label for="gp-feedback-comment"><?php esc_html_e( 'Comment (optional)', 'gatherpress' ); ?></label>
				<textarea id="gp-feedback-comment" name="comment" rows="3" placeholder="<?php esc_attr_e( 'What did you think of this event?', 'gatherpress' ); ?>"></textarea>
			</div>
			<button type="submit" class="wp-block-gatherpress-event-feedback__submit">
				<?php esc_html_e( 'Submit Feedback', 'gatherpress' ); ?>
			</button>
			<div class="wp-block-gatherpress-event-feedback__message" role="alert" aria-live="polite" hidden></div>
		</form>
	<?php elseif ( $gatherpress_user_feedback ) : ?>
		<p class="wp-block-gatherpress-event-feedback__thankyou">
			<?php esc_html_e( 'Thank you for your feedback!', 'gatherpress' ); ?>
		</p>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<p class="wp-block-gatherpress-event-feedback__login-prompt">
			<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#feedback' ) ); ?>">
				<?php esc_html_e( 'Log in to leave feedback', 'gatherpress' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<!-- Existing reviews -->
	<?php if ( ! empty( $gatherpress_reviews ) ) : ?>
		<div class="wp-block-gatherpress-event-feedback__reviews">
			<?php foreach ( $gatherpress_reviews as $gatherpress_review ) : ?>
				<?php
				$gatherpress_rating = (int) get_comment_meta( (int) $gatherpress_review->comment_ID, Event_Feedback::META_RATING, true );
				?>
				<div class="wp-block-gatherpress-event-feedback__review">
					<div class="wp-block-gatherpress-event-feedback__review-header">
						<?php echo get_avatar( $gatherpress_review->user_id, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<strong><?php echo esc_html( $gatherpress_review->comment_author ); ?></strong>
						<span class="wp-block-gatherpress-event-feedback__review-stars">
							<?php
							for ( $gatherpress_i = 1; $gatherpress_i <= 5; $gatherpress_i++ ) {
								$gatherpress_cls = $gatherpress_i <= $gatherpress_rating ? 'filled' : 'empty';
								echo '<span class="wp-block-gatherpress-event-feedback__star wp-block-gatherpress-event-feedback__star--' . esc_attr( $gatherpress_cls ) . '">&#9733;</span>';
							}
							?>
						</span>
					</div>
					<?php if ( ! empty( $gatherpress_review->comment_content ) ) : ?>
						<p class="wp-block-gatherpress-event-feedback__review-text">
							<?php echo esc_html( $gatherpress_review->comment_content ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>
