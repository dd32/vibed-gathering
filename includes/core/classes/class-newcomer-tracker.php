<?php
/**
 * Newcomer tracking for GatherPress.
 *
 * Tracks first-time attendees at events to help organizers welcome newcomers
 * and measure community growth.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Newcomer_Tracker.
 *
 * Identifies first-time event attendees and flags them for organizers.
 *
 * @since 1.0.0
 */
class Newcomer_Tracker {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * User meta key for tracking first event attendance.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_FIRST_EVENT = 'gatherpress_first_event_id';

	/**
	 * Comment meta key for flagging newcomers in RSVP records.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const RSVP_META_NEWCOMER = 'gatherpress_is_newcomer';

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
		// Track newcomers when RSVP status changes.
		add_action( 'gatherpress_rsvp_updated', array( $this, 'track_newcomer' ), 10, 3 );
	}

	/**
	 * Track whether a user is a newcomer when they RSVP.
	 *
	 * A newcomer is someone who has never attended an event on this site before.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id The event post ID.
	 * @param int    $user_id  The user ID.
	 * @param string $status   The RSVP status.
	 * @return void
	 */
	public function track_newcomer( int $event_id, int $user_id, string $status ): void {
		if ( 'attending' !== $status || empty( $user_id ) ) {
			return;
		}

		if ( ! $this->is_newcomer( $user_id ) ) {
			return;
		}

		// Flag this RSVP as a newcomer.
		$rsvp_query = Rsvp_Query::get_instance();
		$rsvp       = $rsvp_query->get_rsvp(
			array(
				'post_id' => $event_id,
				'user_id' => $user_id,
			)
		);

		if ( $rsvp ) {
			update_comment_meta( (int) $rsvp->comment_ID, self::RSVP_META_NEWCOMER, 1 );
		}

		// Record this as the user's first event.
		$first_event = get_user_meta( $user_id, self::META_FIRST_EVENT, true );
		if ( empty( $first_event ) ) {
			update_user_meta( $user_id, self::META_FIRST_EVENT, $event_id );
		}
	}

	/**
	 * Check if a user is a newcomer (has never attended an event on this site).
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if the user is a newcomer.
	 */
	public static function is_newcomer( int $user_id ): bool {
		$rsvp_query = Rsvp_Query::get_instance();

		// Check if the user has any prior attending RSVPs on this site.
		$prior_rsvps = $rsvp_query->get_rsvps(
			array(
				'user_id' => $user_id,
				'status'  => 'approve',
			)
		);

		// Filter to only "attending" status RSVPs.
		$attending_count = 0;
		foreach ( $prior_rsvps as $rsvp ) {
			$terms = wp_get_object_terms( (int) $rsvp->comment_ID, '_gatherpress_rsvp_status', array( 'fields' => 'slugs' ) );
			if ( is_array( $terms ) && in_array( 'attending', $terms, true ) ) {
				++$attending_count;
			}
		}

		// If they have 0 or 1 attending RSVPs (the current one), they're a newcomer.
		return $attending_count <= 1;
	}

	/**
	 * Get newcomer count for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return int The number of newcomers attending this event.
	 */
	public static function get_newcomer_count( int $event_id ): int {
		$comments = get_comments(
			array(
				'post_id'    => $event_id,
				'type'       => 'gatherpress_rsvp',
				'status'     => 'approve',
				'meta_key'   => self::RSVP_META_NEWCOMER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return count( $comments );
	}
}
