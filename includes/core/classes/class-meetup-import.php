<?php
/**
 * Meetup.com data import tool for GatherPress.
 *
 * Provides tools to import event data from Meetup.com API responses
 * or CSV exports, facilitating migration for WordPress communities
 * transitioning from Meetup.com to GatherPress.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Error;

/**
 * Class Meetup_Import.
 *
 * Handles importing event data from Meetup.com into GatherPress.
 *
 * @since 1.0.0
 */
class Meetup_Import {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_import_page' ) );
	}

	/**
	 * Add the import page to the admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_import_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Event::POST_TYPE,
			__( 'Import from Meetup.com', 'gatherpress' ),
			__( 'Import Meetup', 'gatherpress' ),
			'manage_options',
			'gatherpress-meetup-import',
			array( $this, 'render_import_page' )
		);
	}

	/**
	 * Render the import admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$gatherpress_message = '';
		$gatherpress_type    = '';

		// Handle form submission.
		if ( isset( $_POST['gatherpress_meetup_import_nonce'] ) && wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['gatherpress_meetup_import_nonce'] ) ),
			'gatherpress_meetup_import'
		) ) {
			if ( isset( $_POST['meetup_json'] ) ) {
				$gatherpress_json   = sanitize_textarea_field( wp_unslash( $_POST['meetup_json'] ) );
				$gatherpress_result = $this->import_from_json( $gatherpress_json );

				if ( is_wp_error( $gatherpress_result ) ) {
					$gatherpress_message = $gatherpress_result->get_error_message();
					$gatherpress_type    = 'error';
				} else {
					$gatherpress_message = sprintf(
						/* translators: %d: number of events imported. */
						__( 'Successfully imported %d events.', 'gatherpress' ),
						$gatherpress_result
					);
					$gatherpress_type = 'success';
				}
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import from Meetup.com', 'gatherpress' ); ?></h1>

			<?php if ( ! empty( $gatherpress_message ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $gatherpress_type ); ?> is-dismissible">
					<p><?php echo esc_html( $gatherpress_message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width: 800px;">
				<h2><?php esc_html_e( 'Import Events from JSON', 'gatherpress' ); ?></h2>
				<p><?php esc_html_e( 'Paste the JSON data from a Meetup.com API response or export. The importer will create GatherPress events from the data.', 'gatherpress' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'gatherpress_meetup_import', 'gatherpress_meetup_import_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="meetup_json"><?php esc_html_e( 'Meetup.com JSON Data', 'gatherpress' ); ?></label>
							</th>
							<td>
								<textarea name="meetup_json" id="meetup_json" rows="15" class="large-text code" placeholder='[{"name": "Event Title", "time": 1234567890000, ...}]'></textarea>
								<p class="description">
									<?php esc_html_e( 'Paste the JSON array of events from the Meetup.com API.', 'gatherpress' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Import Events', 'gatherpress' ) ); ?>
				</form>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Data Format', 'gatherpress' ); ?></h2>
				<p><?php esc_html_e( 'The importer expects a JSON array of event objects with the following fields:', 'gatherpress' ); ?></p>
				<ul style="list-style: disc; padding-left: 20px;">
					<li><code>name</code> - <?php esc_html_e( 'Event title (required)', 'gatherpress' ); ?></li>
					<li><code>description</code> - <?php esc_html_e( 'Event description (HTML)', 'gatherpress' ); ?></li>
					<li><code>time</code> - <?php esc_html_e( 'Start time (Unix timestamp in milliseconds)', 'gatherpress' ); ?></li>
					<li><code>duration</code> - <?php esc_html_e( 'Duration in milliseconds (default: 2 hours)', 'gatherpress' ); ?></li>
					<li><code>venue.name</code> - <?php esc_html_e( 'Venue name', 'gatherpress' ); ?></li>
					<li><code>venue.address_1</code> - <?php esc_html_e( 'Venue address', 'gatherpress' ); ?></li>
					<li><code>venue.city</code> - <?php esc_html_e( 'Venue city', 'gatherpress' ); ?></li>
					<li><code>venue.country</code> - <?php esc_html_e( 'Venue country', 'gatherpress' ); ?></li>
					<li><code>link</code> - <?php esc_html_e( 'Original Meetup.com event URL', 'gatherpress' ); ?></li>
					<li><code>rsvp_limit</code> - <?php esc_html_e( 'Attendance limit', 'gatherpress' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Import events from a JSON string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gatherpress_json The JSON data string.
	 * @return int|WP_Error Number of events imported, or WP_Error on failure.
	 */
	public function import_from_json( string $gatherpress_json ) {
		$gatherpress_data = json_decode( $gatherpress_json, true );

		if ( ! is_array( $gatherpress_data ) ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON data.', 'gatherpress' ) );
		}

		// Handle both array of events and single event object.
		if ( isset( $gatherpress_data['name'] ) ) {
			$gatherpress_data = array( $gatherpress_data );
		}

		$gatherpress_imported = 0;

		foreach ( $gatherpress_data as $gatherpress_meetup_event ) {
			$gatherpress_result = $this->import_single_event( $gatherpress_meetup_event );

			if ( ! is_wp_error( $gatherpress_result ) ) {
				++$gatherpress_imported;
			}
		}

		return $gatherpress_imported;
	}

	/**
	 * Import a single event from Meetup.com data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $gatherpress_meetup_event The Meetup.com event data.
	 * @return int|WP_Error The new event post ID, or WP_Error on failure.
	 */
	private function import_single_event( array $gatherpress_meetup_event ) {
		$gatherpress_title = $gatherpress_meetup_event['name'] ?? '';

		if ( empty( $gatherpress_title ) ) {
			return new WP_Error( 'missing_title', __( 'Event title is required.', 'gatherpress' ) );
		}

		// Parse datetime from Meetup.com format (milliseconds timestamp).
		$gatherpress_start_ms = $gatherpress_meetup_event['time'] ?? 0;
		$gatherpress_duration = $gatherpress_meetup_event['duration'] ?? 7200000;

		if ( empty( $gatherpress_start_ms ) ) {
			return new WP_Error( 'missing_time', __( 'Event start time is required.', 'gatherpress' ) );
		}

		$gatherpress_start_ts = intval( $gatherpress_start_ms / 1000 );
		$gatherpress_end_ts   = $gatherpress_start_ts + intval( $gatherpress_duration / 1000 );

		$gatherpress_timezone = $gatherpress_meetup_event['utc_offset'] ?? 0;
		$gatherpress_tz_str   = wp_timezone_string();

		// Create the event post.
		$gatherpress_description = $gatherpress_meetup_event['description'] ?? '';
		$gatherpress_description = wp_kses_post( $gatherpress_description );

		$gatherpress_post_id = wp_insert_post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => sanitize_text_field( $gatherpress_title ),
				'post_content' => $gatherpress_description,
				'post_status'  => 'publish',
			)
		);

		if ( 0 === $gatherpress_post_id ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create event post.', 'gatherpress' ) );
		}

		// Save datetime data.
		$gatherpress_event = new Event( $gatherpress_post_id );
		$gatherpress_event->save_datetimes(
			array(
				'datetime_start' => gmdate( Event::DATETIME_FORMAT, $gatherpress_start_ts ),
				'datetime_end'   => gmdate( Event::DATETIME_FORMAT, $gatherpress_end_ts ),
				'timezone'       => $gatherpress_tz_str,
			)
		);

		// Save attendance limit if set.
		$gatherpress_rsvp_limit = $gatherpress_meetup_event['rsvp_limit'] ?? 0;
		if ( ! empty( $gatherpress_rsvp_limit ) ) {
			update_post_meta( $gatherpress_post_id, 'gatherpress_max_attendance_limit', intval( $gatherpress_rsvp_limit ) );
		}

		// Store original Meetup.com URL as meta for reference.
		$gatherpress_link = $gatherpress_meetup_event['link'] ?? '';
		if ( ! empty( $gatherpress_link ) ) {
			update_post_meta( $gatherpress_post_id, 'gatherpress_meetup_original_url', esc_url_raw( $gatherpress_link ) );
		}

		// Store original Meetup.com event ID.
		$gatherpress_meetup_id = $gatherpress_meetup_event['id'] ?? '';
		if ( ! empty( $gatherpress_meetup_id ) ) {
			update_post_meta( $gatherpress_post_id, 'gatherpress_meetup_event_id', sanitize_text_field( $gatherpress_meetup_id ) );
		}

		/**
		 * Fires after a Meetup.com event is imported.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $gatherpress_post_id     The new event post ID.
		 * @param array $gatherpress_meetup_event The original Meetup.com data.
		 */
		do_action( 'gatherpress_meetup_event_imported', $gatherpress_post_id, $gatherpress_meetup_event );

		return $gatherpress_post_id;
	}
}
