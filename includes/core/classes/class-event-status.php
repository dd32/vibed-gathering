<?php
/**
 * Event status management for GatherPress.
 *
 * Handles event lifecycle status transitions including cancellation,
 * postponement, and attendee notifications for status changes.
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
 * Class Event_Status.
 *
 * Manages event lifecycle status including cancellation, postponement,
 * and attendee notifications.
 *
 * @since 1.0.0
 */
class Event_Status {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post meta key for event status.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_STATUS = 'gatherpress_event_status';

	/**
	 * Post meta key for cancellation reason.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_CANCEL_REASON = 'gatherpress_cancel_reason';

	/**
	 * Status: Scheduled (default).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_SCHEDULED = 'scheduled';

	/**
	 * Status: Cancelled.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Status: Postponed.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_POSTPONED = 'postponed';

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
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_filter( 'the_title', array( $this, 'add_status_to_title' ), 10, 2 );
		add_filter( 'gatherpress_event_structured_data', array( $this, 'update_structured_data_status' ), 10, 2 );
	}

	/**
	 * Register post meta for event status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		register_post_meta(
			Event::POST_TYPE,
			self::META_STATUS,
			array(
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => self::STATUS_SCHEDULED,
			)
		);

		register_post_meta(
			Event::POST_TYPE,
			self::META_CANCEL_REASON,
			array(
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_textarea_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			)
		);
	}

	/**
	 * Register REST API endpoints for event status management.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/cancel',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'cancel_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'reason'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/restore',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'restore_event' ),
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
	}

	/**
	 * Get the status of an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return string The event status.
	 */
	public static function get_status( int $event_id ): string {
		$status = get_post_meta( $event_id, self::META_STATUS, true );

		return ! empty( $status ) ? $status : self::STATUS_SCHEDULED;
	}

	/**
	 * Check if an event is cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return bool True if the event is cancelled.
	 */
	public static function is_cancelled( int $event_id ): bool {
		return self::STATUS_CANCELLED === self::get_status( $event_id );
	}

	/**
	 * Handle the cancel event REST request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function cancel_event( WP_REST_Request $request ): WP_REST_Response {
		$event_id = intval( $request->get_param( 'post_id' ) );
		$reason   = $request->get_param( 'reason' );

		update_post_meta( $event_id, self::META_STATUS, self::STATUS_CANCELLED );

		if ( ! empty( $reason ) ) {
			update_post_meta( $event_id, self::META_CANCEL_REASON, $reason );
		}

		// Notify all attending RSVPs.
		$this->notify_attendees_of_cancellation( $event_id, $reason );

		/**
		 * Fires after an event is cancelled.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $event_id The event post ID.
		 * @param string $reason   The cancellation reason.
		 */
		do_action( 'gatherpress_event_cancelled', $event_id, $reason );

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => self::STATUS_CANCELLED,
			)
		);
	}

	/**
	 * Handle the restore event REST request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function restore_event( WP_REST_Request $request ): WP_REST_Response {
		$event_id = intval( $request->get_param( 'post_id' ) );

		update_post_meta( $event_id, self::META_STATUS, self::STATUS_SCHEDULED );
		delete_post_meta( $event_id, self::META_CANCEL_REASON );

		/**
		 * Fires after a cancelled event is restored.
		 *
		 * @since 1.0.0
		 *
		 * @param int $event_id The event post ID.
		 */
		do_action( 'gatherpress_event_restored', $event_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => self::STATUS_SCHEDULED,
			)
		);
	}

	/**
	 * Notify all attending RSVPs that an event has been cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $reason   The cancellation reason.
	 * @return void
	 */
	private function notify_attendees_of_cancellation( int $event_id, string $reason ): void {
		$event = new Event( $event_id );
		if ( ! $event->event || ! $event->rsvp ) {
			return;
		}

		$responses = $event->rsvp->responses();

		if ( empty( $responses['attending']['records'] ) ) {
			return;
		}

		$event_title = get_the_title( $event_id );

		/* translators: %s: event title. */
		$subject = sprintf( __( 'Cancelled: %s', 'gatherpress' ), $event_title );

		$message = sprintf(
			/* translators: %s: event title. */
			__( 'We regret to inform you that <strong>%s</strong> has been cancelled.', 'gatherpress' ),
			esc_html( $event_title )
		);

		if ( ! empty( $reason ) ) {
			$message .= '<br><br>';
			/* translators: %s: cancellation reason. */
			$message .= sprintf( __( '<strong>Reason:</strong> %s', 'gatherpress' ), esc_html( $reason ) );
		}

		foreach ( $responses['attending']['records'] as $record ) {
			$user = get_user_by( 'id', $record['id'] );
			if ( ! $user ) {
				continue;
			}

			$content = Email::render_email_body(
				array(
					'greeting' => sprintf(
						/* translators: %s: user display name. */
						__( 'Hi %s,', 'gatherpress' ),
						$user->display_name
					),
					'message'  => $message,
					'event_id' => $event_id,
				)
			);

			Email::send( $user->user_email, $subject, $content );
		}
	}

	/**
	 * Add status indicator to event title in front-end display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title   The post title.
	 * @param int    $post_id The post ID.
	 * @return string The modified title.
	 */
	public function add_status_to_title( string $title, int $post_id ): string {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $title;
		}

		// Only modify on front-end, not in admin.
		if ( is_admin() ) {
			return $title;
		}

		$status = self::get_status( $post_id );

		if ( self::STATUS_CANCELLED === $status ) {
			/* translators: %s: event title. */
			return sprintf( __( '[CANCELLED] %s', 'gatherpress' ), $title );
		}

		if ( self::STATUS_POSTPONED === $status ) {
			/* translators: %s: event title. */
			return sprintf( __( '[POSTPONED] %s', 'gatherpress' ), $title );
		}

		return $title;
	}

	/**
	 * Update structured data event status based on cancellation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema  The structured data array.
	 * @param int   $post_id The event post ID.
	 * @return array The updated schema array.
	 */
	public function update_structured_data_status( array $schema, int $post_id ): array {
		$status = self::get_status( $post_id );

		switch ( $status ) {
			case self::STATUS_CANCELLED:
				$schema['eventStatus'] = 'https://schema.org/EventCancelled';
				break;

			case self::STATUS_POSTPONED:
				$schema['eventStatus'] = 'https://schema.org/EventPostponed';
				break;

			default:
				$schema['eventStatus'] = 'https://schema.org/EventScheduled';
				break;
		}

		return $schema;
	}

	/**
	 * Get all valid event statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Associative array of status slug => label.
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_SCHEDULED => __( 'Scheduled', 'gatherpress' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'gatherpress' ),
			self::STATUS_POSTPONED => __( 'Postponed', 'gatherpress' ),
		);
	}
}
