<?php
/**
 * Calendar feed for GatherPress.
 *
 * Provides subscribable iCal feeds containing all upcoming events for a site.
 * In a multisite deployment, each group site gets its own calendar feed URL.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP;
use WP_Query;

/**
 * Class Calendar_Feed.
 *
 * Generates iCal feeds for subscribing to event calendars.
 *
 * @since 1.0.0
 */
class Calendar_Feed {
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
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'parse_request', array( $this, 'handle_feed_request' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'wp_head', array( $this, 'add_feed_discovery_link' ) );
	}

	/**
	 * Register rewrite rules for the calendar feed endpoint.
	 *
	 * Creates a /calendar.ics URL that serves the iCal feed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^calendar\.ics$',
			'index.php?gatherpress_calendar_feed=1',
			'top'
		);

		add_rewrite_tag( '%gatherpress_calendar_feed%', '1' );
	}

	/**
	 * Add custom query vars.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[] Modified query vars.
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'gatherpress_calendar_feed';

		return $vars;
	}

	/**
	 * Handle calendar feed requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP $wp The WordPress request object.
	 * @return void
	 */
	public function handle_feed_request( WP $wp ): void {
		if ( ! isset( $wp->query_vars['gatherpress_calendar_feed'] ) ) {
			return;
		}

		$ics_content = $this->generate_feed();

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="calendar.ics"' );
		header( 'Cache-Control: public, max-age=3600' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ICS content must not be HTML-escaped.
		echo $ics_content;
		Utility::safe_exit();
	}

	/**
	 * Generate the full iCal feed content.
	 *
	 * @since 1.0.0
	 *
	 * @return string The iCal feed content.
	 */
	public function generate_feed(): string {
		$events = $this->get_upcoming_events();

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//GatherPress//Calendar//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			sprintf( 'X-WR-CALNAME:%s', $this->ics_escape( get_bloginfo( 'name' ) ) ),
			sprintf( 'X-WR-CALDESC:%s', $this->ics_escape( get_bloginfo( 'description' ) ) ),
		);

		foreach ( $events as $event_post ) {
			$event_lines = $this->generate_vevent( $event_post->ID );
			$lines       = array_merge( $lines, $event_lines );
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Generate a VEVENT block for a single event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The event post ID.
	 * @return string[] Array of ICS lines for this event.
	 */
	private function generate_vevent( int $post_id ): array {
		$event    = new Event( $post_id );
		$datetime = $event->get_datetime();

		if ( empty( $datetime['datetime_start_gmt'] ) ) {
			return array();
		}

		$start_gmt = str_replace( array( '-', ':', ' ' ), array( '', '', 'T' ), $datetime['datetime_start_gmt'] ) . 'Z';
		$end_gmt   = str_replace( array( '-', ':', ' ' ), array( '', '', 'T' ), $datetime['datetime_end_gmt'] ) . 'Z';

		$venue_info = $event->get_venue_information();
		$venue_name = $venue_info['name'] ?? '';

		$description = wp_strip_all_tags( get_the_excerpt( $post_id ) );

		$lines = array(
			'BEGIN:VEVENT',
			sprintf( 'UID:%s@%s', $post_id, wp_parse_url( home_url(), PHP_URL_HOST ) ),
			sprintf( 'DTSTART:%s', $start_gmt ),
			sprintf( 'DTEND:%s', $end_gmt ),
			sprintf( 'DTSTAMP:%s', gmdate( 'Ymd\THis\Z' ) ),
			sprintf( 'SUMMARY:%s', $this->ics_escape( get_the_title( $post_id ) ) ),
			sprintf( 'URL:%s', esc_url_raw( get_permalink( $post_id ) ) ),
		);

		if ( ! empty( $description ) ) {
			$lines[] = sprintf( 'DESCRIPTION:%s', $this->ics_escape( $description ) );
		}

		if ( ! empty( $venue_name ) ) {
			$lines[] = sprintf( 'LOCATION:%s', $this->ics_escape( $venue_name ) );
		}

		// Add online event link.
		$online_link = get_post_meta( $post_id, 'gatherpress_online_event_link', true );
		if ( ! empty( $online_link ) ) {
			$lines[] = sprintf( 'X-ONLINE-LINK:%s', esc_url_raw( $online_link ) );
		}

		$lines[] = 'END:VEVENT';

		return $lines;
	}

	/**
	 * Get upcoming events for the feed.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Optional. Number of events to include. Default 50.
	 * @return \WP_Post[] Array of event posts.
	 */
	private function get_upcoming_events( int $limit = 50 ): array {
		$query = new WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'gatherpress_datetime_end_gmt',
						'value'   => gmdate( Event::DATETIME_FORMAT ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'orderby'        => 'meta_value',
				'meta_key'       => 'gatherpress_datetime_start_gmt', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'          => 'ASC',
			)
		);

		return $query->posts;
	}

	/**
	 * Add feed autodiscovery link to the HTML head.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_feed_discovery_link(): void {
		printf(
			'<link rel="alternate" type="text/calendar" title="%s" href="%s" />' . "\n",
			esc_attr(
				sprintf(
					/* translators: %s: site name. */
					__( '%s Calendar', 'gatherpress' ),
					get_bloginfo( 'name' )
				)
			),
			esc_url( home_url( '/calendar.ics' ) )
		);
	}

	/**
	 * Escape a string for use in ICS content.
	 *
	 * Handles special characters according to RFC 5545.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to escape.
	 * @return string The escaped text.
	 */
	private function ics_escape( string $text ): string {
		// Escape backslashes first, then other special chars.
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ',', '\\,', $text );
		$text = str_replace( ';', '\\;', $text );
		$text = str_replace( "\n", '\\n', $text );
		$text = str_replace( "\r", '', $text );

		return $text;
	}
}
