<?php
/**
 * Member profile enhancements for GatherPress.
 *
 * Extends WordPress user profiles with event attendance history,
 * group memberships, and WordPress.org profile integration.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

/**
 * Class Member_Profile.
 *
 * Provides enhanced member profiles with event history and WordPress.org integration.
 *
 * @since 1.0.0
 */
class Member_Profile {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * User meta key for WordPress.org username.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_WPORG_USERNAME = 'gatherpress_wporg_username';

	/**
	 * User meta key for user bio.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_BIO = 'gatherpress_bio';

	/**
	 * User meta key for user location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_LOCATION = 'gatherpress_location';

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
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
	}

	/**
	 * Register REST API endpoints for member profiles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/member/profile',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_profile' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/member/profile/update',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_profile' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'wporg_username' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_user',
					),
					'bio'            => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'location'       => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get a member's public profile data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The profile data.
	 */
	public function get_profile( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_user_id = intval( $request->get_param( 'user_id' ) );
		$gatherpress_user    = get_user_by( 'id', $gatherpress_user_id );

		if ( ! $gatherpress_user ) {
			return new WP_REST_Response( array( 'error' => 'User not found.' ), 404 );
		}

		$gatherpress_profile = self::get_profile_data( $gatherpress_user_id );

		return new WP_REST_Response( $gatherpress_profile );
	}

	/**
	 * Update the current user's profile.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function update_profile( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_user_id = get_current_user_id();

		$gatherpress_fields = array(
			'wporg_username' => self::META_WPORG_USERNAME,
			'bio'            => self::META_BIO,
			'location'       => self::META_LOCATION,
		);

		foreach ( $gatherpress_fields as $gatherpress_param => $gatherpress_meta_key ) {
			$gatherpress_value = $request->get_param( $gatherpress_param );
			if ( null !== $gatherpress_value ) {
				update_user_meta( $gatherpress_user_id, $gatherpress_meta_key, $gatherpress_value );
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'profile' => self::get_profile_data( $gatherpress_user_id ),
			)
		);
	}

	/**
	 * Get profile data for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_user_id The user ID.
	 * @return array The profile data.
	 */
	public static function get_profile_data( int $gatherpress_user_id ): array {
		$gatherpress_user = get_user_by( 'id', $gatherpress_user_id );

		if ( ! $gatherpress_user ) {
			return array();
		}

		$gatherpress_wporg_username = get_user_meta( $gatherpress_user_id, self::META_WPORG_USERNAME, true );
		$gatherpress_bio            = get_user_meta( $gatherpress_user_id, self::META_BIO, true );
		$gatherpress_location       = get_user_meta( $gatherpress_user_id, self::META_LOCATION, true );

		// Get event attendance stats.
		$gatherpress_stats = self::get_attendance_stats( $gatherpress_user_id );

		// Get group memberships.
		$gatherpress_groups = array();
		if ( is_multisite() ) {
			$gatherpress_group_objects = Group::get_user_groups( $gatherpress_user_id );
			foreach ( $gatherpress_group_objects as $gatherpress_group ) {
				$gatherpress_groups[] = array(
					'blog_id' => $gatherpress_group->get_blog_id(),
					'name'    => $gatherpress_group->get_name(),
					'url'     => $gatherpress_group->get_url(),
					'role'    => $gatherpress_group->get_member_role( $gatherpress_user_id ),
				);
			}
		}

		return array(
			'user_id'          => $gatherpress_user_id,
			'display_name'     => $gatherpress_user->display_name,
			'avatar_url'       => get_avatar_url( $gatherpress_user_id, array( 'size' => 192 ) ),
			'bio'              => $gatherpress_bio,
			'location'         => $gatherpress_location,
			'wporg_username'   => $gatherpress_wporg_username,
			'wporg_profile'    => ! empty( $gatherpress_wporg_username )
				? 'https://profiles.wordpress.org/' . $gatherpress_wporg_username . '/'
				: '',
			'registered'       => $gatherpress_user->user_registered,
			'groups'           => $gatherpress_groups,
			'events_attended'  => $gatherpress_stats['attended'],
			'events_organized' => $gatherpress_stats['organized'],
		);
	}

	/**
	 * Get event attendance statistics for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_user_id The user ID.
	 * @return array{attended: int, organized: int} Attendance stats.
	 */
	public static function get_attendance_stats( int $gatherpress_user_id ): array {
		$gatherpress_rsvp_query = Rsvp_Query::get_instance();

		// Count events where user has an attending RSVP.
		$gatherpress_rsvps = $gatherpress_rsvp_query->get_rsvps(
			array(
				'user_id' => $gatherpress_user_id,
				'status'  => 'approve',
			)
		);

		$gatherpress_attended = 0;
		foreach ( $gatherpress_rsvps as $gatherpress_rsvp ) {
			$gatherpress_terms = wp_get_object_terms(
				(int) $gatherpress_rsvp->comment_ID,
				'_gatherpress_rsvp_status',
				array( 'fields' => 'slugs' )
			);
			if ( is_array( $gatherpress_terms ) && in_array( 'attending', $gatherpress_terms, true ) ) {
				++$gatherpress_attended;
			}
		}

		// Count events organized by this user.
		$gatherpress_organized_query = new \WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => $gatherpress_user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		return array(
			'attended'  => $gatherpress_attended,
			'organized' => $gatherpress_organized_query->found_posts,
		);
	}

	/**
	 * Render additional profile fields on the WordPress user profile page.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_User $gatherpress_user The user object.
	 * @return void
	 */
	public function render_profile_fields( WP_User $gatherpress_user ): void {
		$gatherpress_wporg = get_user_meta( $gatherpress_user->ID, self::META_WPORG_USERNAME, true );
		$gatherpress_bio   = get_user_meta( $gatherpress_user->ID, self::META_BIO, true );
		$gatherpress_loc   = get_user_meta( $gatherpress_user->ID, self::META_LOCATION, true );
		?>
		<h3><?php esc_html_e( 'GatherPress Profile', 'gatherpress' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="gatherpress_wporg_username"><?php esc_html_e( 'WordPress.org Username', 'gatherpress' ); ?></label></th>
				<td>
					<input type="text" name="gatherpress_wporg_username" id="gatherpress_wporg_username" value="<?php echo esc_attr( $gatherpress_wporg ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Your WordPress.org profile username for community recognition.', 'gatherpress' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="gatherpress_bio"><?php esc_html_e( 'Bio', 'gatherpress' ); ?></label></th>
				<td>
					<textarea name="gatherpress_bio" id="gatherpress_bio" rows="4" class="regular-text"><?php echo esc_textarea( $gatherpress_bio ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="gatherpress_location"><?php esc_html_e( 'Location', 'gatherpress' ); ?></label></th>
				<td>
					<input type="text" name="gatherpress_location" id="gatherpress_location" value="<?php echo esc_attr( $gatherpress_loc ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'City, Country (e.g., "Melbourne, Australia").', 'gatherpress' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save additional profile fields from the WordPress user profile page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_user_id The user ID being saved.
	 * @return void
	 */
	public function save_profile_fields( int $gatherpress_user_id ): void {
		if ( ! current_user_can( 'edit_user', $gatherpress_user_id ) ) {
			return;
		}

		// Nonce verification is handled by WordPress core's profile update system.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['gatherpress_wporg_username'] ) ) {
			update_user_meta(
				$gatherpress_user_id,
				self::META_WPORG_USERNAME,
				sanitize_user( wp_unslash( $_POST['gatherpress_wporg_username'] ) )
			);
		}

		if ( isset( $_POST['gatherpress_bio'] ) ) {
			update_user_meta(
				$gatherpress_user_id,
				self::META_BIO,
				sanitize_textarea_field( wp_unslash( $_POST['gatherpress_bio'] ) )
			);
		}

		if ( isset( $_POST['gatherpress_location'] ) ) {
			update_user_meta(
				$gatherpress_user_id,
				self::META_LOCATION,
				sanitize_text_field( wp_unslash( $_POST['gatherpress_location'] ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
