<?php
/**
 * Dormancy detection for GatherPress groups.
 *
 * Monitors group activity and identifies groups that may be becoming inactive.
 * Sends alerts to network administrators when groups are at risk or dormant.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Dormancy_Detector.
 *
 * Detects inactive groups and sends notifications to administrators.
 *
 * @since 1.0.0
 */
class Dormancy_Detector {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron hook for dormancy checks.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CRON_HOOK = 'gatherpress_check_dormancy';

	/**
	 * Days of inactivity before a group is considered "at risk".
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const AT_RISK_DAYS = 60;

	/**
	 * Days of inactivity before a group is considered "dormant".
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DORMANT_DAYS = 90;

	/**
	 * Blog option key for last event date.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_LAST_EVENT = 'gatherpress_last_event_date';

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
		add_action( self::CRON_HOOK, array( $this, 'check_all_groups' ) );
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( 'transition_post_status', array( $this, 'track_event_activity' ), 10, 3 );
	}

	/**
	 * Schedule the daily cron event for dormancy checks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_cron(): void {
		// Only schedule on the main site to avoid redundant cron on every group site.
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Track event activity by recording when events are published.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $gatherpress_new New post status.
	 * @param string   $gatherpress_old Old post status.
	 * @param \WP_Post $gatherpress_post The post object.
	 * @return void
	 */
	public function track_event_activity( string $gatherpress_new, string $gatherpress_old, \WP_Post $gatherpress_post ): void {
		if ( Event::POST_TYPE !== $gatherpress_post->post_type ) {
			return;
		}

		if ( 'publish' === $gatherpress_new ) {
			update_option( self::OPTION_LAST_EVENT, current_time( 'mysql', true ) );
		}
	}

	/**
	 * Check all groups for dormancy.
	 *
	 * Runs via daily cron. Only executes on the main site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function check_all_groups(): void {
		if ( ! is_multisite() ) {
			return;
		}

		// Only run on the main site.
		if ( ! is_main_site() ) {
			return;
		}

		$gatherpress_groups = Group::get_all_groups( array( 'number' => 500 ) );

		foreach ( $gatherpress_groups as $gatherpress_group ) {
			$this->check_group( $gatherpress_group );
		}
	}

	/**
	 * Check a single group for dormancy.
	 *
	 * @since 1.0.0
	 *
	 * @param Group $gatherpress_group The group to check.
	 * @return string|null The dormancy status: 'at_risk', 'dormant', or null if active.
	 */
	public function check_group( Group $gatherpress_group ): ?string {
		$gatherpress_last_event = get_blog_option( $gatherpress_group->get_blog_id(), self::OPTION_LAST_EVENT, '' );

		if ( empty( $gatherpress_last_event ) ) {
			// No events ever published - check site creation date.
			$gatherpress_blog = get_blog_details( $gatherpress_group->get_blog_id() );
			$gatherpress_last_event = $gatherpress_blog ? $gatherpress_blog->registered : '';
		}

		if ( empty( $gatherpress_last_event ) ) {
			return null;
		}

		$gatherpress_last_ts = strtotime( $gatherpress_last_event );
		$gatherpress_days    = (int) floor( ( time() - $gatherpress_last_ts ) / DAY_IN_SECONDS );

		if ( $gatherpress_days >= self::DORMANT_DAYS ) {
			$gatherpress_current = $gatherpress_group->get_status();
			if ( 'dormant' !== $gatherpress_current ) {
				update_blog_option( $gatherpress_group->get_blog_id(), Group::OPTION_STATUS, 'dormant' );
				$this->notify_dormant( $gatherpress_group, $gatherpress_days );
			}
			return 'dormant';
		}

		if ( $gatherpress_days >= self::AT_RISK_DAYS ) {
			$this->notify_at_risk( $gatherpress_group, $gatherpress_days );
			return 'at_risk';
		}

		return null;
	}

	/**
	 * Notify administrators that a group is at risk of becoming dormant.
	 *
	 * @since 1.0.0
	 *
	 * @param Group $gatherpress_group The at-risk group.
	 * @param int   $gatherpress_days  Days since last event.
	 * @return void
	 */
	private function notify_at_risk( Group $gatherpress_group, int $gatherpress_days ): void {
		$gatherpress_admin_email = get_site_option( 'admin_email', get_option( 'admin_email' ) );

		/* translators: %s: group name. */
		$gatherpress_subject = sprintf( __( 'At-Risk Group: %s', 'gatherpress' ), $gatherpress_group->get_name() );

		$gatherpress_content = Email::render_email_body(
			array(
				'greeting' => __( 'Group Activity Alert', 'gatherpress' ),
				'message'  => sprintf(
					/* translators: 1: group name, 2: number of days. */
					__( '<strong>%1$s</strong> has not had any events in <strong>%2$d days</strong>. This group may need attention to prevent it from becoming dormant.', 'gatherpress' ),
					esc_html( $gatherpress_group->get_name() ),
					$gatherpress_days
				),
				'button_text' => __( 'View Group', 'gatherpress' ),
				'button_url'  => $gatherpress_group->get_url(),
			)
		);

		Email::send( $gatherpress_admin_email, $gatherpress_subject, $gatherpress_content );

		/**
		 * Fires when a group is detected as at-risk.
		 *
		 * @since 1.0.0
		 *
		 * @param int $blog_id The group blog ID.
		 * @param int $days    Days since last event.
		 */
		do_action( 'gatherpress_group_at_risk', $gatherpress_group->get_blog_id(), $gatherpress_days );
	}

	/**
	 * Notify administrators that a group has become dormant.
	 *
	 * @since 1.0.0
	 *
	 * @param Group $gatherpress_group The dormant group.
	 * @param int   $gatherpress_days  Days since last event.
	 * @return void
	 */
	private function notify_dormant( Group $gatherpress_group, int $gatherpress_days ): void {
		$gatherpress_admin_email = get_site_option( 'admin_email', get_option( 'admin_email' ) );

		/* translators: %s: group name. */
		$gatherpress_subject = sprintf( __( 'Dormant Group: %s', 'gatherpress' ), $gatherpress_group->get_name() );

		$gatherpress_content = Email::render_email_body(
			array(
				'greeting' => __( 'Group Dormancy Alert', 'gatherpress' ),
				'message'  => sprintf(
					/* translators: 1: group name, 2: number of days. */
					__( '<strong>%1$s</strong> has been inactive for <strong>%2$d days</strong> and has been marked as dormant. Consider reaching out to the organizers or archiving the group.', 'gatherpress' ),
					esc_html( $gatherpress_group->get_name() ),
					$gatherpress_days
				),
				'button_text' => __( 'View Group', 'gatherpress' ),
				'button_url'  => $gatherpress_group->get_url(),
			)
		);

		Email::send( $gatherpress_admin_email, $gatherpress_subject, $gatherpress_content );

		/**
		 * Fires when a group is detected as dormant.
		 *
		 * @since 1.0.0
		 *
		 * @param int $blog_id The group blog ID.
		 * @param int $days    Days since last event.
		 */
		do_action( 'gatherpress_group_dormant', $gatherpress_group->get_blog_id(), $gatherpress_days );
	}

	/**
	 * Unschedule the dormancy cron event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function unschedule_cron(): void {
		$gatherpress_timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $gatherpress_timestamp ) {
			wp_unschedule_event( $gatherpress_timestamp, self::CRON_HOOK );
		}
	}
}
