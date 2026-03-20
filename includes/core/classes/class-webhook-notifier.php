<?php
/**
 * Webhook notification system for GatherPress.
 *
 * Sends notifications to external services (Slack, Discord, etc.) when
 * key events occur such as new RSVPs, event creation, and membership changes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Webhook_Notifier.
 *
 * Sends webhook notifications to configured URLs when key platform events occur.
 *
 * @since 1.0.0
 */
class Webhook_Notifier {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Option key for the webhook URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_WEBHOOK_URL = 'gatherpress_webhook_url';

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
		add_action( 'gatherpress_rsvp_updated', array( $this, 'on_rsvp' ), 10, 3 );
		add_action( 'gatherpress_event_cancelled', array( $this, 'on_event_cancelled' ), 10, 2 );
		add_action( 'gatherpress_group_member_added', array( $this, 'on_member_joined' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'on_event_published' ), 10, 3 );
	}

	/**
	 * Get the configured webhook URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The webhook URL, or empty string if not configured.
	 */
	public static function get_webhook_url(): string {
		return get_option( self::OPTION_WEBHOOK_URL, '' );
	}

	/**
	 * Send a webhook notification for an RSVP update.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id The event post ID.
	 * @param int    $user_id  The user ID.
	 * @param string $status   The RSVP status.
	 * @return void
	 */
	public function on_rsvp( int $event_id, int $user_id, string $status ): void {
		if ( 'attending' !== $status ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		$name = $user ? $user->display_name : __( 'Someone', 'gatherpress' );

		$this->send(
			sprintf(
				/* translators: 1: user name, 2: event title. */
				__( '%1$s is attending %2$s', 'gatherpress' ),
				$name,
				get_the_title( $event_id )
			),
			get_the_permalink( $event_id )
		);
	}

	/**
	 * Send a webhook notification when an event is cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $reason   The cancellation reason.
	 * @return void
	 */
	public function on_event_cancelled( int $event_id, string $reason ): void {
		$message = sprintf(
			/* translators: %s: event title. */
			__( 'Event cancelled: %s', 'gatherpress' ),
			get_the_title( $event_id )
		);

		if ( ! empty( $reason ) ) {
			$message .= ' - ' . $reason;
		}

		$this->send( $message, get_the_permalink( $event_id ) );
	}

	/**
	 * Send a webhook notification when a member joins a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $blog_id The blog ID.
	 * @param int    $user_id The user ID.
	 * @param string $role    The membership role.
	 * @return void
	 */
	public function on_member_joined( int $blog_id, int $user_id, string $role ): void {
		$user       = get_user_by( 'id', $user_id );
		$name       = $user ? $user->display_name : __( 'Someone', 'gatherpress' );
		$group_name = get_blog_option( $blog_id, 'blogname', '' );

		$this->send(
			sprintf(
				/* translators: 1: user name, 2: group name. */
				__( '%1$s joined %2$s', 'gatherpress' ),
				$name,
				$group_name
			)
		);
	}

	/**
	 * Send a webhook notification when an event is published.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       The post object.
	 * @return void
	 */
	public function on_event_published( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( Event::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		$this->send(
			sprintf(
				/* translators: %s: event title. */
				__( 'New event: %s', 'gatherpress' ),
				get_the_title( $post->ID )
			),
			get_the_permalink( $post->ID )
		);
	}

	/**
	 * Send a webhook notification.
	 *
	 * Supports Slack-compatible webhook format with optional URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The notification text.
	 * @param string $url  Optional. A URL to include.
	 * @return bool True if sent successfully.
	 */
	private function send( string $text, string $url = '' ): bool {
		$webhook_url = self::get_webhook_url();

		if ( empty( $webhook_url ) ) {
			return false;
		}

		$payload = array( 'text' => $text );

		if ( ! empty( $url ) ) {
			$payload['text'] .= "\n" . $url;
		}

		/**
		 * Filters the webhook payload before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $payload     The webhook payload.
		 * @param string $text        The notification text.
		 * @param string $url         The optional URL.
		 * @param string $webhook_url The webhook endpoint URL.
		 */
		$payload = apply_filters( 'gatherpress_webhook_payload', $payload, $text, $url, $webhook_url );

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $payload ),
				'timeout'  => 5,
				'blocking' => false,
			)
		);

		return ! is_wp_error( $response );
	}
}
