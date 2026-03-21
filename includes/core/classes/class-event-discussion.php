<?php
/**
 * Event discussion and photo gallery support for GatherPress.
 *
 * Enables threaded discussions on event pages (separate from RSVP comments)
 * and provides photo gallery functionality for post-event media sharing.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Event_Discussion.
 *
 * Manages event discussions and photo galleries.
 *
 * @since 1.0.0
 */
class Event_Discussion {
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
		// Ensure regular comments show on events (filter out RSVP comments).
		add_filter( 'comments_template_query_args', array( $this, 'filter_event_comments' ) );
		add_filter( 'get_comments_number', array( $this, 'filter_comment_count' ), 10, 2 );

		// Register event gallery meta.
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	/**
	 * Filter comment queries on event pages to exclude RSVP comments.
	 *
	 * WordPress displays all comments by default. This ensures only regular
	 * user discussion comments show in the comments template, not RSVP records.
	 *
	 * @since 1.0.0
	 *
	 * @param array $gatherpress_args Comment query arguments.
	 * @return array Modified query arguments.
	 */
	public function filter_event_comments( array $gatherpress_args ): array {
		if ( Event::POST_TYPE === get_post_type() ) {
			$gatherpress_args['type'] = 'comment';
		}

		return $gatherpress_args;
	}

	/**
	 * Filter the comment count on events to exclude RSVP comments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_count   The current comment count.
	 * @param int $gatherpress_post_id The post ID.
	 * @return int The filtered comment count.
	 */
	public function filter_comment_count( int $gatherpress_count, int $gatherpress_post_id ): int {
		if ( Event::POST_TYPE !== get_post_type( $gatherpress_post_id ) ) {
			return $gatherpress_count;
		}

		$gatherpress_comments = get_comments(
			array(
				'post_id' => $gatherpress_post_id,
				'type'    => 'comment',
				'count'   => true,
			)
		);

		return (int) $gatherpress_comments;
	}

	/**
	 * Register post meta for the event photo gallery.
	 *
	 * Stores an array of attachment IDs as the event gallery.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		register_post_meta(
			Event::POST_TYPE,
			'gatherpress_event_gallery',
			array(
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => array( $this, 'sanitize_gallery_meta' ),
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize the gallery meta value.
	 *
	 * Expects a JSON array of attachment IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gatherpress_value The raw meta value.
	 * @return string The sanitized JSON string.
	 */
	public function sanitize_gallery_meta( string $gatherpress_value ): string {
		$gatherpress_ids = json_decode( $gatherpress_value, true );

		if ( ! is_array( $gatherpress_ids ) ) {
			return '';
		}

		// Ensure all values are valid attachment IDs.
		$gatherpress_sanitized = array_filter(
			array_map( 'absint', $gatherpress_ids ),
			static function ( $gatherpress_id ) {
				return 'attachment' === get_post_type( $gatherpress_id );
			}
		);

		return wp_json_encode( array_values( $gatherpress_sanitized ) );
	}

	/**
	 * Get the gallery attachment IDs for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event post ID.
	 * @return int[] Array of attachment IDs.
	 */
	public static function get_gallery( int $gatherpress_event_id ): array {
		$gatherpress_raw = get_post_meta( $gatherpress_event_id, 'gatherpress_event_gallery', true );

		if ( empty( $gatherpress_raw ) ) {
			return array();
		}

		$gatherpress_ids = json_decode( $gatherpress_raw, true );

		if ( ! is_array( $gatherpress_ids ) ) {
			return array();
		}

		return array_map( 'absint', $gatherpress_ids );
	}

	/**
	 * Get the discussion comment count for an event.
	 *
	 * Returns only the count of regular comments, not RSVP comments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event post ID.
	 * @return int The discussion comment count.
	 */
	public static function get_discussion_count( int $gatherpress_event_id ): int {
		$gatherpress_count = get_comments(
			array(
				'post_id' => $gatherpress_event_id,
				'type'    => 'comment',
				'count'   => true,
			)
		);

		return (int) $gatherpress_count;
	}
}
