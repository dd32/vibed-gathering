<?php
/**
 * Event check-in system for GatherPress.
 *
 * Provides QR code-based event check-in, attendance marking, and
 * no-show tracking for organizers.
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
use WP_Error;

/**
 * Class Event_Checkin.
 *
 * Manages event check-in functionality with QR codes and attendance tracking.
 *
 * @since 1.0.0
 */
class Event_Checkin {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Comment meta key for check-in status.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_CHECKED_IN = 'gatherpress_checked_in';

	/**
	 * Comment meta key for check-in timestamp.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_CHECKIN_TIME = 'gatherpress_checkin_time';

	/**
	 * Comment meta key for no-show flag.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_NO_SHOW = 'gatherpress_no_show';

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
	}

	/**
	 * Register REST API endpoints for event check-in.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/checkin',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'checkin_attendee' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/checkin-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_checkin_status' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
				),
			)
		);

		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/qr-data',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_qr_data' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
				),
			)
		);
	}

	/**
	 * Check in an attendee.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	public function checkin_attendee( WP_REST_Request $request ) {
		$gatherpress_event_id = intval( $request->get_param( 'post_id' ) );
		$gatherpress_user_id  = intval( $request->get_param( 'user_id' ) );

		$gatherpress_rsvp_query = Rsvp_Query::get_instance();
		$gatherpress_rsvp       = $gatherpress_rsvp_query->get_rsvp(
			array(
				'post_id' => $gatherpress_event_id,
				'user_id' => $gatherpress_user_id,
			)
		);

		if ( ! $gatherpress_rsvp ) {
			return new WP_Error(
				'not_attending',
				__( 'User does not have an RSVP for this event.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		$gatherpress_comment_id = (int) $gatherpress_rsvp->comment_ID;

		// Mark as checked in.
		update_comment_meta( $gatherpress_comment_id, self::META_CHECKED_IN, 1 );
		update_comment_meta( $gatherpress_comment_id, self::META_CHECKIN_TIME, current_time( 'mysql', true ) );

		// Remove no-show flag if set.
		delete_comment_meta( $gatherpress_comment_id, self::META_NO_SHOW );

		/**
		 * Fires after an attendee checks in to an event.
		 *
		 * @since 1.0.0
		 *
		 * @param int $gatherpress_event_id The event post ID.
		 * @param int $gatherpress_user_id  The user ID.
		 */
		do_action( 'gatherpress_attendee_checked_in', $gatherpress_event_id, $gatherpress_user_id );

		return new WP_REST_Response(
			array(
				'success'    => true,
				'checked_in' => true,
				'time'       => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Get check-in status for all attendees of an event.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The check-in status data.
	 */
	public function get_checkin_status( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_event_id = intval( $request->get_param( 'post_id' ) );
		$gatherpress_rsvp     = new Rsvp( $gatherpress_event_id );
		$gatherpress_data     = $gatherpress_rsvp->responses();
		$gatherpress_records  = $gatherpress_data['attending']['records'] ?? array();

		$gatherpress_attendees = array();
		$gatherpress_checked   = 0;
		$gatherpress_total     = 0;

		foreach ( $gatherpress_records as $gatherpress_record ) {
			$gatherpress_user       = get_user_by( 'id', $gatherpress_record['id'] );
			$gatherpress_comment_id = $gatherpress_record['commentId'] ?? 0;
			$gatherpress_is_checked = (bool) get_comment_meta( $gatherpress_comment_id, self::META_CHECKED_IN, true );
			$gatherpress_is_noshow  = (bool) get_comment_meta( $gatherpress_comment_id, self::META_NO_SHOW, true );

			if ( $gatherpress_is_checked ) {
				++$gatherpress_checked;
			}
			++$gatherpress_total;

			$gatherpress_attendees[] = array(
				'user_id'    => $gatherpress_record['id'],
				'name'       => $gatherpress_user ? $gatherpress_user->display_name : '',
				'avatar_url' => get_avatar_url( $gatherpress_record['id'], array( 'size' => 48 ) ),
				'checked_in' => $gatherpress_is_checked,
				'no_show'    => $gatherpress_is_noshow,
				'checkin_time' => $gatherpress_is_checked
					? get_comment_meta( $gatherpress_comment_id, self::META_CHECKIN_TIME, true )
					: null,
			);
		}

		return new WP_REST_Response(
			array(
				'attendees'     => $gatherpress_attendees,
				'total'         => $gatherpress_total,
				'checked_in'    => $gatherpress_checked,
				'not_checked_in' => $gatherpress_total - $gatherpress_checked,
			)
		);
	}

	/**
	 * Get QR code data for an attendee's RSVP.
	 *
	 * Returns data that can be encoded into a QR code for check-in.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The QR data.
	 */
	public function get_qr_data( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_event_id = intval( $request->get_param( 'post_id' ) );
		$gatherpress_user_id  = get_current_user_id();

		// Generate a check-in token (hash of user ID + event ID + secret).
		$gatherpress_token = wp_hash( $gatherpress_user_id . '-' . $gatherpress_event_id . '-checkin' );

		// Build check-in URL that organizer's scanner can use.
		$gatherpress_checkin_url = add_query_arg(
			array(
				'gp_checkin' => 1,
				'event'      => $gatherpress_event_id,
				'user'       => $gatherpress_user_id,
				'token'      => $gatherpress_token,
			),
			home_url()
		);

		return new WP_REST_Response(
			array(
				'qr_data'    => $gatherpress_checkin_url,
				'event_id'   => $gatherpress_event_id,
				'event_title' => get_the_title( $gatherpress_event_id ),
			)
		);
	}

	/**
	 * Get the check-in count for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event post ID.
	 * @return int The number of checked-in attendees.
	 */
	public static function get_checkin_count( int $gatherpress_event_id ): int {
		$gatherpress_comments = get_comments(
			array(
				'post_id'    => $gatherpress_event_id,
				'type'       => 'gatherpress_rsvp',
				'status'     => 'approve',
				'meta_key'   => self::META_CHECKED_IN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return count( $gatherpress_comments );
	}
}
