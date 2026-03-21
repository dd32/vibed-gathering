<?php
/**
 * Render Group Application Form block.
 *
 * Displays a form for users to apply to create a new community group.
 * Only shown on the main site to logged-in users.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Only show on main site.
if ( is_multisite() && ! is_main_site() ) {
	return;
}

$gatherpress_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'wp-block-gatherpress-application-form' )
);
?>

<div <?php echo $gatherpress_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="wp-block-gatherpress-application-form__login">
			<p><?php esc_html_e( 'Please log in to submit a group application.', 'gatherpress' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="wp-block-gatherpress-application-form__login-btn">
				<?php esc_html_e( 'Log In', 'gatherpress' ); ?>
			</a>
		</div>
	<?php else : ?>
		<h2 class="wp-block-gatherpress-application-form__title">
			<?php esc_html_e( 'Apply to Start a Group', 'gatherpress' ); ?>
		</h2>
		<p class="wp-block-gatherpress-application-form__intro">
			<?php esc_html_e( 'Fill out this form to apply for a new community group. Applications are reviewed by administrators.', 'gatherpress' ); ?>
		</p>

		<form class="wp-block-gatherpress-application-form__form" data-gp-application-form data-i18n-submitting="<?php esc_attr_e( 'Submitting...', 'gatherpress' ); ?>" data-i18n-submit="<?php esc_attr_e( 'Submit Application', 'gatherpress' ); ?>" data-i18n-success="<?php esc_attr_e( 'Your application has been submitted!', 'gatherpress' ); ?>" data-i18n-error="<?php esc_attr_e( 'Something went wrong.', 'gatherpress' ); ?>">
			<?php wp_nonce_field( 'wp_rest', '_wpnonce' ); ?>

			<div class="wp-block-gatherpress-application-form__field">
				<label for="gp-app-name"><?php esc_html_e( 'Group Name', 'gatherpress' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" id="gp-app-name" name="group_name" required placeholder="<?php esc_attr_e( 'e.g., WordPress Melbourne', 'gatherpress' ); ?>" />
			</div>

			<div class="wp-block-gatherpress-application-form__field">
				<label for="gp-app-desc"><?php esc_html_e( 'Description', 'gatherpress' ); ?> <span aria-hidden="true">*</span></label>
				<textarea id="gp-app-desc" name="description" required rows="4" placeholder="<?php esc_attr_e( 'Describe what your group will focus on and what kind of events you plan to organize.', 'gatherpress' ); ?>"></textarea>
			</div>

			<div class="wp-block-gatherpress-application-form__row">
				<div class="wp-block-gatherpress-application-form__field">
					<label for="gp-app-city"><?php esc_html_e( 'City', 'gatherpress' ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="gp-app-city" name="city" required placeholder="<?php esc_attr_e( 'Melbourne', 'gatherpress' ); ?>" />
				</div>
				<div class="wp-block-gatherpress-application-form__field">
					<label for="gp-app-country"><?php esc_html_e( 'Country', 'gatherpress' ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="gp-app-country" name="country" required placeholder="<?php esc_attr_e( 'Australia', 'gatherpress' ); ?>" />
				</div>
			</div>

			<div class="wp-block-gatherpress-application-form__field">
				<label for="gp-app-freq"><?php esc_html_e( 'Planned Event Frequency', 'gatherpress' ); ?></label>
				<select id="gp-app-freq" name="frequency">
					<option value="weekly"><?php esc_html_e( 'Weekly', 'gatherpress' ); ?></option>
					<option value="biweekly"><?php esc_html_e( 'Biweekly', 'gatherpress' ); ?></option>
					<option value="monthly" selected><?php esc_html_e( 'Monthly', 'gatherpress' ); ?></option>
				</select>
			</div>

			<div class="wp-block-gatherpress-application-form__field">
				<label for="gp-app-info"><?php esc_html_e( 'About You (Optional)', 'gatherpress' ); ?></label>
				<textarea id="gp-app-info" name="organizer_info" rows="3" placeholder="<?php esc_attr_e( 'Tell us about your experience organizing events or your connection to the WordPress community.', 'gatherpress' ); ?>"></textarea>
			</div>

			<div class="wp-block-gatherpress-application-form__submit">
				<button type="submit" class="wp-block-gatherpress-application-form__submit-btn">
					<?php esc_html_e( 'Submit Application', 'gatherpress' ); ?>
				</button>
			</div>

			<div class="wp-block-gatherpress-application-form__message" role="alert" aria-live="polite" hidden></div>
		</form>
	<?php endif; ?>
</div>
