<?php
/**
 * Email notification system for GatherPress.
 *
 * Handles sending beautiful HTML email notifications for events, RSVPs,
 * group announcements, and other platform communications.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Email.
 *
 * Manages all email notifications sent by GatherPress. All emails use
 * beautiful HTML templates with consistent branding.
 *
 * @since 1.0.0
 */
class Email {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron hook for event reminders.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REMINDER_CRON_HOOK = 'gatherpress_send_event_reminders';

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
	/**
	 * Cron hook for weekly digest emails.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DIGEST_CRON_HOOK = 'gatherpress_send_weekly_digest';

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_rsvp_updated', array( $this, 'send_rsvp_confirmation' ), 10, 4 );
		add_action( 'gatherpress_waitlist_promoted', array( $this, 'send_waitlist_promotion' ), 10, 2 );
		add_action( self::REMINDER_CRON_HOOK, array( $this, 'process_event_reminders' ) );
		add_action( self::REMINDER_CRON_HOOK, array( $this, 'process_followup_emails' ) );
		add_action( self::DIGEST_CRON_HOOK, array( $this, 'send_weekly_digest' ) );
		add_action( 'init', array( $this, 'schedule_reminder_cron' ) );
		add_action( 'init', array( $this, 'schedule_digest_cron' ) );
		add_action( 'gatherpress_recurring_event_created', array( $this, 'notify_new_recurring_event' ), 10, 2 );
	}

	/**
	 * Schedule the hourly cron event for event reminders.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_reminder_cron(): void {
		if ( ! wp_next_scheduled( self::REMINDER_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::REMINDER_CRON_HOOK );
		}
	}

	/**
	 * Schedule the weekly digest cron event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_digest_cron(): void {
		if ( ! wp_next_scheduled( self::DIGEST_CRON_HOOK ) ) {
			// Schedule for Monday mornings at 9am local time.
			$gatherpress_next_monday = strtotime( 'next Monday 9:00' );
			wp_schedule_event( $gatherpress_next_monday, 'weekly', self::DIGEST_CRON_HOOK );
		}
	}

	/**
	 * Unschedule the reminder cron event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function unschedule_reminder_cron(): void {
		$gatherpress_ts = wp_next_scheduled( self::REMINDER_CRON_HOOK );
		if ( $gatherpress_ts ) {
			wp_unschedule_event( $gatherpress_ts, self::REMINDER_CRON_HOOK );
		}

		$gatherpress_digest_ts = wp_next_scheduled( self::DIGEST_CRON_HOOK );
		if ( $gatherpress_digest_ts ) {
			wp_unschedule_event( $gatherpress_digest_ts, self::DIGEST_CRON_HOOK );
		}
	}

	/**
	 * Send an RSVP confirmation email.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id The event post ID.
	 * @param int    $user_id  The user ID.
	 * @param string $status   The RSVP status (attending, not_attending, waiting_list).
	 * @param int    $guests   Number of guests.
	 * @return void
	 */
	public function send_rsvp_confirmation( int $event_id, int $user_id, string $status, int $guests = 0 ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$event = new Event( $event_id );
		if ( ! $event->event ) {
			return;
		}

		$status_labels = array(
			'attending'     => __( 'Attending', 'gatherpress' ),
			'not_attending' => __( 'Not Attending', 'gatherpress' ),
			'waiting_list'  => __( 'Waiting List', 'gatherpress' ),
		);

		$status_label = $status_labels[ $status ] ?? $status;

		/* translators: %s: event title. */
		$subject = sprintf( __( 'RSVP Confirmed: %s', 'gatherpress' ), get_the_title( $event_id ) );

		$content = self::render_email_body(
			array(
				'greeting'    => sprintf(
					/* translators: %s: user display name. */
					__( 'Hi %s,', 'gatherpress' ),
					$user->display_name
				),
				'message'     => sprintf(
					/* translators: 1: RSVP status, 2: event title. */
					__( 'Your RSVP status has been updated to <strong>%1$s</strong> for the event <strong>%2$s</strong>.', 'gatherpress' ),
					esc_html( $status_label ),
					esc_html( get_the_title( $event_id ) )
				),
				'event_id'    => $event_id,
				'button_text' => __( 'View Event', 'gatherpress' ),
				'button_url'  => get_the_permalink( $event_id ),
			)
		);

		self::send( $user->user_email, $subject, $content );
	}

	/**
	 * Send a waitlist promotion notification email.
	 *
	 * Called when a user is automatically promoted from the waiting list
	 * to attending status because a spot opened up.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @param int $user_id  The user ID who was promoted.
	 * @return void
	 */
	public function send_waitlist_promotion( int $event_id, int $user_id ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$event = new Event( $event_id );
		if ( ! $event->event ) {
			return;
		}

		/* translators: %s: event title. */
		$subject = sprintf( __( 'You\'re in! %s', 'gatherpress' ), get_the_title( $event_id ) );

		$content = self::render_email_body(
			array(
				'greeting'    => sprintf(
					/* translators: %s: user display name. */
					__( 'Great news, %s!', 'gatherpress' ),
					$user->display_name
				),
				'message'     => sprintf(
					/* translators: %s: event title. */
					__( 'A spot has opened up and you\'ve been moved from the waiting list to <strong>Attending</strong> for <strong>%s</strong>.', 'gatherpress' ),
					esc_html( get_the_title( $event_id ) )
				),
				'event_id'    => $event_id,
				'button_text' => __( 'View Event Details', 'gatherpress' ),
				'button_url'  => get_the_permalink( $event_id ),
			)
		);

		self::send( $user->user_email, $subject, $content );
	}

	/**
	 * Process event reminders for upcoming events.
	 *
	 * Sends reminder emails 24 hours before events to all attending RSVPs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_event_reminders(): void {
		global $wpdb;

		$table    = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$now      = gmdate( Event::DATETIME_FORMAT );
		$tomorrow = gmdate( Event::DATETIME_FORMAT, time() + DAY_IN_SECONDS );

		// Find events starting in the next 24 hours that haven't had reminders sent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				'SELECT post_id FROM %i WHERE datetime_start_gmt BETWEEN %s AND %s',
				$table,
				$now,
				$tomorrow
			)
		);

		if ( ! is_array( $events ) ) {
			return;
		}

		foreach ( $events as $event_row ) {
			$event_id = (int) $event_row->post_id;

			// Check if reminder has already been sent.
			$reminder_sent = get_post_meta( $event_id, 'gatherpress_reminder_sent', true );
			if ( $reminder_sent ) {
				continue;
			}

			$this->send_event_reminder( $event_id );

			// Mark reminder as sent.
			update_post_meta( $event_id, 'gatherpress_reminder_sent', 1 );
		}
	}

	/**
	 * Send event reminder emails to all attending RSVPs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return void
	 */
	public function send_event_reminder( int $event_id ): void {
		$event = new Event( $event_id );
		if ( ! $event->event ) {
			return;
		}

		$rsvp      = $event->rsvp;
		$responses = $rsvp ? $rsvp->responses() : array();

		if ( empty( $responses['attending']['records'] ) ) {
			return;
		}

		/* translators: %s: event title. */
		$subject = sprintf( __( 'Reminder: %s is tomorrow!', 'gatherpress' ), get_the_title( $event_id ) );

		foreach ( $responses['attending']['records'] as $record ) {
			$user = get_user_by( 'id', $record['id'] );
			if ( ! $user ) {
				continue;
			}

			$content = self::render_email_body(
				array(
					'greeting'    => sprintf(
						/* translators: %s: user display name. */
						__( 'Hi %s,', 'gatherpress' ),
						$user->display_name
					),
					'message'     => sprintf(
						/* translators: %s: event title. */
						__( 'This is a reminder that <strong>%s</strong> is happening tomorrow!', 'gatherpress' ),
						esc_html( get_the_title( $event_id ) )
					),
					'event_id'    => $event_id,
					'button_text' => __( 'View Event Details', 'gatherpress' ),
					'button_url'  => get_the_permalink( $event_id ),
				)
			);

			self::send( $user->user_email, $subject, $content );
		}
	}

	/**
	 * Send an announcement email to all members of a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $blog_id The group blog ID (multisite).
	 * @param string $subject The email subject.
	 * @param string $message The announcement message.
	 * @param int    $sender_id The user ID sending the announcement.
	 * @return int Number of emails sent.
	 */
	public static function send_group_announcement( int $blog_id, string $subject, string $message, int $sender_id ): int {
		$group  = new Group( $blog_id );
		$sender = get_user_by( 'id', $sender_id );

		if ( ! $group->is_valid() || ! $sender ) {
			return 0;
		}

		$members = $group->get_members( '', 1000 );
		$sent    = 0;

		$content = self::render_email_body(
			array(
				'greeting'    => sprintf(
					/* translators: %s: group name. */
					__( 'Announcement from %s', 'gatherpress' ),
					$group->get_name()
				),
				'message'     => wp_kses_post( nl2br( $message ) ),
				'footer_text' => sprintf(
					/* translators: 1: sender name, 2: group name. */
					__( 'Sent by %1$s, organizer of %2$s', 'gatherpress' ),
					$sender->display_name,
					$group->get_name()
				),
			)
		);

		foreach ( $members as $member ) {
			// Don't send to the sender.
			if ( $member->ID === $sender_id ) {
				continue;
			}

			if ( self::send( $member->user_email, $subject, $content ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Send notification for a new recurring event instance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $new_event_id      The new event post ID.
	 * @param int $template_event_id The template event post ID.
	 * @return void
	 */
	public function notify_new_recurring_event( int $new_event_id, int $template_event_id ): void {
		/**
		 * Fires when a recurring event notification should be sent.
		 * Allows themes and plugins to hook into recurring event notifications.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_event_id      The new event post ID.
		 * @param int $template_event_id The template event post ID.
		 */
		do_action( 'gatherpress_recurring_event_notification', $new_event_id, $template_event_id );
	}

	/**
	 * Process post-event follow-up emails.
	 *
	 * Sends thank-you emails to attendees of events that ended in the last
	 * 24 hours, with links to the feedback survey.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_followup_emails(): void {
		global $wpdb;

		$gatherpress_table    = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$gatherpress_now      = gmdate( Event::DATETIME_FORMAT );
		$gatherpress_24h_ago  = gmdate( Event::DATETIME_FORMAT, time() - DAY_IN_SECONDS );

		// Find events that ended in the last 24 hours.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$gatherpress_events = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				'SELECT post_id FROM %i WHERE datetime_end_gmt BETWEEN %s AND %s',
				$gatherpress_table,
				$gatherpress_24h_ago,
				$gatherpress_now
			)
		);

		if ( ! is_array( $gatherpress_events ) ) {
			return;
		}

		foreach ( $gatherpress_events as $gatherpress_row ) {
			$gatherpress_event_id = (int) $gatherpress_row->post_id;

			// Check if follow-up has already been sent.
			$gatherpress_sent = get_post_meta( $gatherpress_event_id, 'gatherpress_followup_sent', true );
			if ( $gatherpress_sent ) {
				continue;
			}

			// Skip cancelled events.
			if ( Event_Status::is_cancelled( $gatherpress_event_id ) ) {
				continue;
			}

			$this->send_followup_email( $gatherpress_event_id );
			update_post_meta( $gatherpress_event_id, 'gatherpress_followup_sent', 1 );
		}
	}

	/**
	 * Send a post-event follow-up email to all attending RSVPs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event post ID.
	 * @return void
	 */
	private function send_followup_email( int $gatherpress_event_id ): void {
		$gatherpress_event = new Event( $gatherpress_event_id );
		if ( ! $gatherpress_event->event || ! $gatherpress_event->rsvp ) {
			return;
		}

		$gatherpress_responses = $gatherpress_event->rsvp->responses();

		if ( empty( $gatherpress_responses['attending']['records'] ) ) {
			return;
		}

		$gatherpress_title = get_the_title( $gatherpress_event_id );

		/* translators: %s: event title. */
		$gatherpress_subject = sprintf( __( 'Thanks for attending: %s', 'gatherpress' ), $gatherpress_title );

		foreach ( $gatherpress_responses['attending']['records'] as $gatherpress_record ) {
			$gatherpress_user = get_user_by( 'id', $gatherpress_record['id'] );
			if ( ! $gatherpress_user ) {
				continue;
			}

			$gatherpress_content = self::render_email_body(
				array(
					'greeting'    => sprintf(
						/* translators: %s: user display name. */
						__( 'Thanks for attending, %s!', 'gatherpress' ),
						$gatherpress_user->display_name
					),
					'message'     => sprintf(
						/* translators: %s: event title. */
						__( 'We hope you enjoyed <strong>%s</strong>. We\'d love to hear your feedback to help us make future events even better.', 'gatherpress' ),
						esc_html( $gatherpress_title )
					),
					'event_id'    => $gatherpress_event_id,
					'button_text' => __( 'Leave Feedback', 'gatherpress' ),
					'button_url'  => get_the_permalink( $gatherpress_event_id ) . '#feedback',
				)
			);

			self::send( $gatherpress_user->user_email, $gatherpress_subject, $gatherpress_content );
		}
	}

	/**
	 * Send weekly digest emails to all group members.
	 *
	 * Collects upcoming events for the next 7 days and sends a summary
	 * email to each member of the group. Only runs on group sites (not main).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function send_weekly_digest(): void {
		// Only send digests on group sites, not the main site.
		if ( is_multisite() && is_main_site() ) {
			return;
		}

		$gatherpress_group = new Group();

		if ( ! $gatherpress_group->is_valid() ) {
			return;
		}

		// Get events in the next 7 days.
		$gatherpress_now     = gmdate( Event::DATETIME_FORMAT );
		$gatherpress_7d      = gmdate( Event::DATETIME_FORMAT, time() + ( 7 * DAY_IN_SECONDS ) );
		$gatherpress_query   = new \WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'gatherpress_datetime_start_gmt',
						'value'   => array( $gatherpress_now, $gatherpress_7d ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
				),
				'meta_key'       => 'gatherpress_datetime_start_gmt', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);

		// Don't send empty digests.
		if ( ! $gatherpress_query->have_posts() ) {
			return;
		}

		// Build the events list HTML.
		$gatherpress_events_html = '';
		while ( $gatherpress_query->have_posts() ) {
			$gatherpress_query->the_post();
			$gatherpress_event = new Event( get_the_ID() );

			// Skip cancelled events.
			if ( Event_Status::is_cancelled( get_the_ID() ) ) {
				continue;
			}

			$gatherpress_events_html .= sprintf(
				'<div style="margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 8px;">'
				. '<strong><a href="%s" style="color: #6366f1; text-decoration: none;">%s</a></strong><br>'
				. '<span style="color: #64748b; font-size: 14px;">%s</span>'
				. '</div>',
				esc_url( get_the_permalink() ),
				esc_html( get_the_title() ),
				esc_html( $gatherpress_event->get_display_datetime() )
			);
		}
		wp_reset_postdata();

		if ( empty( $gatherpress_events_html ) ) {
			return;
		}

		$gatherpress_group_name = $gatherpress_group->get_name();

		/* translators: %s: group name. */
		$gatherpress_subject = sprintf( __( 'This Week in %s', 'gatherpress' ), $gatherpress_group_name );

		$gatherpress_members = $gatherpress_group->get_members( '', 1000 );

		foreach ( $gatherpress_members as $gatherpress_member ) {
			$gatherpress_content = self::render_email_body(
				array(
					'greeting' => sprintf(
						/* translators: %s: user display name. */
						__( 'Hi %s,', 'gatherpress' ),
						$gatherpress_member->display_name
					),
					'message'  => sprintf(
						/* translators: %s: group name. */
						__( 'Here are the upcoming events this week in <strong>%s</strong>:', 'gatherpress' ),
						esc_html( $gatherpress_group_name )
					) . '<br><br>' . $gatherpress_events_html,
					'button_text' => __( 'View All Events', 'gatherpress' ),
					'button_url'  => home_url(),
				)
			);

			self::send( $gatherpress_member->user_email, $gatherpress_subject, $gatherpress_content );
		}
	}

	/**
	 * Send an HTML email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject.
	 * @param string $body    HTML email body.
	 * @return bool True if sent successfully.
	 */
	public static function send( string $to, string $subject, string $body ): bool {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', get_bloginfo( 'name' ), get_option( 'admin_email' ) ),
		);

		/**
		 * Filters the email headers before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $headers The email headers.
		 * @param string   $to      The recipient email.
		 * @param string   $subject The email subject.
		 */
		$headers = apply_filters( 'gatherpress_email_headers', $headers, $to, $subject );

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Render the HTML email body using the base template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Email content arguments.
	 *
	 *     @type string $greeting    The greeting line (e.g., "Hi John,").
	 *     @type string $message     The main message content (HTML allowed).
	 *     @type int    $event_id    Optional. Event ID to include event details card.
	 *     @type string $button_text Optional. CTA button text.
	 *     @type string $button_url  Optional. CTA button URL.
	 *     @type string $footer_text Optional. Additional footer text.
	 * }
	 * @return string The rendered HTML email.
	 */
	public static function render_email_body( array $args ): string {
		$defaults = array(
			'greeting'    => '',
			'message'     => '',
			'event_id'    => 0,
			'button_text' => '',
			'button_url'  => '',
			'footer_text' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build event details card if event_id is provided.
		$event_card = '';
		if ( ! empty( $args['event_id'] ) ) {
			$event_card = self::render_event_card( (int) $args['event_id'] );
		}

		// Build CTA button.
		$button = '';
		if ( ! empty( $args['button_text'] ) && ! empty( $args['button_url'] ) ) {
			$button = self::render_button( $args['button_text'], $args['button_url'] );
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Build the complete HTML email.
		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php bloginfo( 'name' ); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f7;">
		<tr>
			<td align="center" style="padding: 40px 20px;">
				<!-- Header -->
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px;">
					<tr>
						<td align="center" style="padding-bottom: 24px;">
							<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration: none; color: #1e293b; font-size: 24px; font-weight: 700;">
								<?php echo esc_html( $site_name ); ?>
							</a>
						</td>
					</tr>
				</table>

				<!-- Main Content -->
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<tr>
						<td style="padding: 40px 40px 32px;">
							<?php if ( ! empty( $args['greeting'] ) ) : ?>
								<p style="margin: 0 0 16px; color: #374151; font-size: 16px; line-height: 1.5;">
									<?php echo esc_html( $args['greeting'] ); ?>
								</p>
							<?php endif; ?>

							<div style="margin: 0 0 24px; color: #374151; font-size: 16px; line-height: 1.6;">
								<?php echo wp_kses_post( $args['message'] ); ?>
							</div>
						</td>
					</tr>

					<?php if ( ! empty( $event_card ) ) : ?>
						<tr>
							<td style="padding: 0 40px 24px;">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_event_card.
								echo $event_card;
								?>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ( ! empty( $button ) ) : ?>
						<tr>
							<td align="center" style="padding: 0 40px 32px;">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_button.
								echo $button;
								?>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<!-- Footer -->
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px;">
					<tr>
						<td align="center" style="padding: 24px 40px;">
							<?php if ( ! empty( $args['footer_text'] ) ) : ?>
								<p style="margin: 0 0 8px; color: #9ca3af; font-size: 13px;">
									<?php echo esc_html( $args['footer_text'] ); ?>
								</p>
							<?php endif; ?>
							<p style="margin: 0; color: #9ca3af; font-size: 13px;">
								<?php
								printf(
									/* translators: %s: site name with link. */
									esc_html__( 'Sent by %s', 'gatherpress' ),
									'<a href="' . esc_url( $site_url ) . '" style="color: #6366f1; text-decoration: none;">' . esc_html( $site_name ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render an event details card for email.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return string The rendered event card HTML.
	 */
	private static function render_event_card( int $event_id ): string {
		$event = new Event( $event_id );
		if ( ! $event->event ) {
			return '';
		}

		$venue_info = $event->get_venue_information();
		$venue_name = $venue_info['name'] ?? '';
		$thumbnail  = get_the_post_thumbnail_url( $event_id, 'medium' );
		$datetime   = $event->get_display_datetime();

		ob_start();
		?>
		<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
			<?php if ( $thumbnail ) : ?>
				<tr>
					<td>
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $event_id ) ); ?>" style="width: 100%; border-radius: 8px 8px 0 0; display: block;">
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<td style="padding: 20px;">
					<h2 style="margin: 0 0 12px; color: #1e293b; font-size: 20px; font-weight: 600;">
						<?php echo esc_html( get_the_title( $event_id ) ); ?>
					</h2>
					<table role="presentation" cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td style="padding: 0 0 8px; color: #6366f1; font-size: 14px; font-weight: 500;">
								&#128197; <?php echo esc_html( $datetime ); ?>
							</td>
						</tr>
						<?php if ( ! empty( $venue_name ) ) : ?>
							<tr>
								<td style="padding: 0 0 8px; color: #64748b; font-size: 14px;">
									&#128205; <?php echo esc_html( $venue_name ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				</td>
			</tr>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a CTA button for email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The button text.
	 * @param string $url  The button URL.
	 * @return string The rendered button HTML.
	 */
	private static function render_button( string $text, string $url ): string {
		return sprintf(
			'<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">'
			. '<tr>'
			. '<td style="background-color: #6366f1; border-radius: 8px;">'
			. '<a href="%s" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">%s</a>'
			. '</td>'
			. '</tr>'
			. '</table>',
			esc_url( $url ),
			esc_html( $text )
		);
	}
}
