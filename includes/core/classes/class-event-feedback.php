<?php
/**
 * Post-event feedback/survey system for GatherPress.
 *
 * Allows attendees to leave feedback after events, including star ratings
 * and text comments. Results are visible to organizers.
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
 * Class Event_Feedback.
 *
 * Manages post-event feedback collection and display.
 *
 * @since 1.0.0
 */
class Event_Feedback {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Comment type for feedback entries.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COMMENT_TYPE = 'gatherpress_feedback';

	/**
	 * Comment meta key for star rating.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_RATING = 'gatherpress_feedback_rating';

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
	 * Register REST API endpoints for event feedback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_feedback' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'rating'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $gatherpress_val ) {
							return is_numeric( $gatherpress_val ) && (int) $gatherpress_val >= 1 && (int) $gatherpress_val <= 5;
						},
					),
					'comment' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/feedback-summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_feedback_summary' ),
				'permission_callback' => '__return_true',
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
	 * Submit feedback for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	public function submit_feedback( WP_REST_Request $request ) {
		$gatherpress_event_id = intval( $request->get_param( 'post_id' ) );
		$gatherpress_rating   = intval( $request->get_param( 'rating' ) );
		$gatherpress_comment  = $request->get_param( 'comment' );
		$gatherpress_user_id  = get_current_user_id();

		// Check if user already submitted feedback.
		$gatherpress_existing = get_comments(
			array(
				'post_id' => $gatherpress_event_id,
				'user_id' => $gatherpress_user_id,
				'type'    => self::COMMENT_TYPE,
				'number'  => 1,
			)
		);

		if ( ! empty( $gatherpress_existing ) ) {
			return new WP_Error(
				'already_submitted',
				__( 'You have already submitted feedback for this event.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$gatherpress_user = get_user_by( 'id', $gatherpress_user_id );

		$gatherpress_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_event_id,
				'comment_content'  => $gatherpress_comment,
				'comment_type'     => self::COMMENT_TYPE,
				'user_id'          => $gatherpress_user_id,
				'comment_author'   => $gatherpress_user ? $gatherpress_user->display_name : '',
				'comment_approved' => 1,
			)
		);

		if ( ! $gatherpress_comment_id ) {
			return new WP_Error(
				'submit_failed',
				__( 'Failed to submit feedback.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		update_comment_meta( $gatherpress_comment_id, self::META_RATING, $gatherpress_rating );

		/**
		 * Fires after event feedback is submitted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $gatherpress_event_id   The event post ID.
		 * @param int $gatherpress_user_id    The user ID.
		 * @param int $gatherpress_rating     The star rating (1-5).
		 * @param int $gatherpress_comment_id The feedback comment ID.
		 */
		do_action( 'gatherpress_feedback_submitted', $gatherpress_event_id, $gatherpress_user_id, $gatherpress_rating, $gatherpress_comment_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Thank you for your feedback!', 'gatherpress' ),
			),
			201
		);
	}

	/**
	 * Get the feedback summary for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The feedback summary.
	 */
	public function get_feedback_summary( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_event_id = intval( $request->get_param( 'post_id' ) );

		$gatherpress_feedbacks = get_comments(
			array(
				'post_id' => $gatherpress_event_id,
				'type'    => self::COMMENT_TYPE,
				'status'  => 'approve',
			)
		);

		$gatherpress_total   = count( $gatherpress_feedbacks );
		$gatherpress_sum     = 0;
		$gatherpress_entries = array();

		foreach ( $gatherpress_feedbacks as $gatherpress_fb ) {
			$gatherpress_rating = (int) get_comment_meta( (int) $gatherpress_fb->comment_ID, self::META_RATING, true );
			$gatherpress_sum   += $gatherpress_rating;

			$gatherpress_entries[] = array(
				'rating'    => $gatherpress_rating,
				'comment'   => $gatherpress_fb->comment_content,
				'author'    => $gatherpress_fb->comment_author,
				'timestamp' => $gatherpress_fb->comment_date_gmt,
			);
		}

		$gatherpress_average = $gatherpress_total > 0
			? round( $gatherpress_sum / $gatherpress_total, 1 )
			: 0;

		return new WP_REST_Response(
			array(
				'total'    => $gatherpress_total,
				'average'  => $gatherpress_average,
				'feedback' => $gatherpress_entries,
			)
		);
	}

	/**
	 * Get the average rating for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event post ID.
	 * @return float The average rating (0-5).
	 */
	public static function get_average_rating( int $gatherpress_event_id ): float {
		$gatherpress_feedbacks = get_comments(
			array(
				'post_id' => $gatherpress_event_id,
				'type'    => self::COMMENT_TYPE,
				'status'  => 'approve',
			)
		);

		if ( empty( $gatherpress_feedbacks ) ) {
			return 0.0;
		}

		$gatherpress_sum = 0;
		foreach ( $gatherpress_feedbacks as $gatherpress_fb ) {
			$gatherpress_sum += (int) get_comment_meta( (int) $gatherpress_fb->comment_ID, self::META_RATING, true );
		}

		return round( $gatherpress_sum / count( $gatherpress_feedbacks ), 1 );
	}
}
