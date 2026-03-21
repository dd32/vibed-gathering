<?php
/**
 * Recurrence generator for creating future event instances from recurring templates.
 *
 * Generates recurring event instances via WP-Cron, creating individual event posts
 * for each occurrence up to a configurable number of weeks ahead.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTime;
use DateTimeZone;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Recurrence_Generator.
 *
 * Generates recurring event instances via WP-Cron.
 * Queries all events with a recurrence rule, calculates upcoming occurrences
 * up to 4 weeks ahead, and creates new event posts for each occurrence that
 * does not already exist.
 *
 * @since 1.0.0
 */
class Recurrence_Generator {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron hook name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CRON_HOOK = 'gatherpress_generate_recurring_events';

	/**
	 * How many weeks ahead to generate instances.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const WEEKS_AHEAD = 4;

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
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
	}

	/**
	 * Register post meta for recurrence data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		register_post_meta(
			Event::POST_TYPE,
			Recurrence::META_KEY,
			array(
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			)
		);

		register_post_meta(
			Event::POST_TYPE,
			Recurrence::TEMPLATE_META_KEY,
			array(
				'auth_callback'     => '__return_false',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
			)
		);
	}

	/**
	 * Schedule the daily cron event if not already scheduled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 *
	 * Called on plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function unschedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the generator for all recurring events on the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$template_ids = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Recurrence::META_KEY,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Recurrence::META_KEY,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		foreach ( $template_ids as $template_id ) {
			self::generate_for_event( (int) $template_id );
		}
	}

	/**
	 * Generate future instances for a single recurring event template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $template_event_id The template event post ID.
	 * @return int[] Array of newly created event post IDs.
	 */
	public static function generate_for_event( int $template_event_id ): array {
		$rule = Recurrence::get_rule( $template_event_id );

		if ( ! $rule ) {
			return array();
		}

		$template_post = get_post( $template_event_id );

		if ( ! $template_post instanceof WP_Post || Event::POST_TYPE !== $template_post->post_type ) {
			return array();
		}

		$event = new Event( $template_event_id );

		// Get the event datetime data from GatherPress custom table.
		$datetime = $event->get_datetime();

		if ( empty( $datetime ) || empty( $datetime['datetime_start_gmt'] ) ) {
			return array();
		}

		$start_gmt = $datetime['datetime_start_gmt'];
		$end_gmt   = $datetime['datetime_end_gmt'];
		$timezone  = $datetime['timezone'];

		if ( empty( $end_gmt ) ) {
			return array();
		}

		$start_dt = new DateTime( $start_gmt, new DateTimeZone( 'UTC' ) );
		$end_dt   = new DateTime( $end_gmt, new DateTimeZone( 'UTC' ) );

		// Calculate the duration of the original event.
		$duration    = $start_dt->diff( $end_dt );
		$time_of_day = $start_dt->format( 'H:i:s' );

		// Calculate occurrences starting from the template's start date.
		// Cap at a reasonable number based on the horizon (WEEKS_AHEAD weeks).
		// For weekly events: 4 weeks = ~4 occurrences. Add buffer for safety.
		$today       = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$horizon     = ( clone $today )->modify( '+' . self::WEEKS_AHEAD . ' weeks' );
		$gatherpress_max_count = self::WEEKS_AHEAD * 2 + 10;
		$occurrences           = Recurrence::calculate_occurrences(
			$rule,
			$start_dt->format( 'Y-m-d' ),
			$gatherpress_max_count
		);

		$created_ids = array();

		foreach ( $occurrences as $occurrence ) {
			// Skip occurrences in the past.
			if ( $occurrence < $today ) {
				continue;
			}

			// Stop once we've gone past the horizon.
			if ( $occurrence > $horizon ) {
				break;
			}

			$occurrence_date = $occurrence->format( 'Y-m-d' );

			// Check if an instance already exists for this date.
			if ( self::instance_exists( $template_event_id, $occurrence_date ) ) {
				continue;
			}

			// Build the new event's start/end datetimes.
			$new_start = new DateTime( $occurrence_date . ' ' . $time_of_day, new DateTimeZone( 'UTC' ) );
			$new_end   = ( clone $new_start )->add( $duration );

			$new_event_id = self::create_instance(
				$template_event_id,
				$template_post,
				$new_start,
				$new_end,
				$timezone
			);

			if ( is_int( $new_event_id ) && 0 < $new_event_id ) {
				$created_ids[] = $new_event_id;
			}
		}

		return $created_ids;
	}

	/**
	 * Check whether an event instance already exists for a given template and date.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $template_event_id The template event post ID.
	 * @param string $date              The occurrence date in Y-m-d format.
	 * @return bool True if an instance already exists.
	 */
	private static function instance_exists( int $template_event_id, string $date ): bool {
		$existing = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => Recurrence::TEMPLATE_META_KEY,
						'value' => $template_event_id,
					),
					array(
						'key'     => 'gatherpress_datetime_start_gmt',
						'value'   => array( $date . ' 00:00:00', $date . ' 23:59:59' ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Create a single event instance from a template.
	 *
	 * Copies the template's title, content, and relevant meta to the new event.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $template_event_id The template event post ID.
	 * @param WP_Post  $template_post     The template post object.
	 * @param DateTime $start             The new event start datetime (UTC).
	 * @param DateTime $end               The new event end datetime (UTC).
	 * @param string   $timezone          The timezone string.
	 * @return int|false New post ID on success, false on failure.
	 */
	private static function create_instance(
		int $template_event_id,
		WP_Post $template_post,
		DateTime $start,
		DateTime $end,
		string $timezone
	) {
		// Create the event post.
		$new_event_id = wp_insert_post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => $template_post->post_title,
				'post_content' => $template_post->post_content,
				'post_excerpt' => $template_post->post_excerpt,
				'post_status'  => 'publish',
				'post_author'  => (int) $template_post->post_author,
			)
		);

		if ( 0 === $new_event_id ) {
			return false;
		}

		// Save datetime data using GatherPress Event class.
		$event = new Event( $new_event_id );

		// Convert UTC times to local times for GatherPress storage.
		$tz        = new DateTimeZone( $timezone );
		$local_start = clone $start;
		$local_start->setTimezone( $tz );
		$local_end = clone $end;
		$local_end->setTimezone( $tz );

		$datetime_json = wp_json_encode(
			array(
				'dateTimeStart' => $local_start->format( Event::DATETIME_FORMAT ),
				'dateTimeEnd'   => $local_end->format( Event::DATETIME_FORMAT ),
				'timezone'      => $timezone,
			)
		);

		// Store the datetime meta that triggers GatherPress to save to the events table.
		update_post_meta( $new_event_id, 'gatherpress_datetime', $datetime_json );

		// Trigger the save_datetimes flow.
		$event->save_datetimes(
			array(
				'datetime_start' => $local_start->format( Event::DATETIME_FORMAT ),
				'datetime_end'   => $local_end->format( Event::DATETIME_FORMAT ),
				'timezone'       => $timezone,
			)
		);

		// Link the new instance back to its template.
		update_post_meta( $new_event_id, Recurrence::TEMPLATE_META_KEY, $template_event_id );

		// Copy attendance-related meta from template.
		$meta_to_copy = array(
			'gatherpress_max_guest_limit',
			'gatherpress_max_attendance_limit',
			'gatherpress_enable_anonymous_rsvp',
			'gatherpress_enable_initial_decline',
		);

		foreach ( $meta_to_copy as $meta_key ) {
			$value = get_post_meta( $template_event_id, $meta_key, true );
			if ( '' !== $value && false !== $value ) {
				update_post_meta( $new_event_id, $meta_key, $value );
			}
		}

		// Copy venue taxonomy terms from template.
		$venue_terms = wp_get_object_terms( $template_event_id, '_gatherpress_venue', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $venue_terms ) && ! empty( $venue_terms ) ) {
			wp_set_object_terms( $new_event_id, $venue_terms, '_gatherpress_venue' );
		}

		// Copy topic taxonomy terms from template.
		$topic_terms = wp_get_object_terms( $template_event_id, 'gatherpress_topic', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $topic_terms ) && ! empty( $topic_terms ) ) {
			wp_set_object_terms( $new_event_id, $topic_terms, 'gatherpress_topic' );
		}

		/**
		 * Fires after a recurring event instance is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_event_id      The new event post ID.
		 * @param int $template_event_id The template event post ID.
		 */
		do_action( 'gatherpress_recurring_event_created', $new_event_id, $template_event_id );

		return $new_event_id;
	}
}
