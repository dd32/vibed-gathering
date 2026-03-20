<?php
/**
 * Recurrence model for recurring events.
 *
 * Handles recurrence rule storage, retrieval, validation, and occurrence calculation
 * for GatherPress events. Supports weekly, biweekly, monthly-by-day, and
 * monthly-by-date patterns.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTime;
use DateTimeZone;
use WP_Error;

/**
 * Class Recurrence.
 *
 * Handles recurrence rule storage, retrieval, and occurrence calculation.
 *
 * @since 1.0.0
 */
class Recurrence {
	/**
	 * Post meta key for the recurrence rule.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY = 'gatherpress_recurrence_rule';

	/**
	 * Post meta key linking a generated instance back to its template event.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TEMPLATE_META_KEY = 'gatherpress_recurrence_template_id';

	/**
	 * Valid frequency values.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const VALID_FREQUENCIES = array(
		'weekly',
		'biweekly',
		'monthly-day',
		'monthly-date',
	);

	/**
	 * Save a recurrence rule to event post meta.
	 *
	 * Validates the rule before saving. The rule is stored as a JSON string.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id The event post ID.
	 * @param array $rule     Recurrence rule with keys: frequency, day_of_week, day_of_month, end_date.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function save_rule( int $event_id, array $rule ) {
		$validated = self::validate_rule( $rule );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		update_post_meta( $event_id, self::META_KEY, wp_json_encode( $validated ) );

		return true;
	}

	/**
	 * Get the recurrence rule for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return array|null The rule array, or null if no rule is set.
	 */
	public static function get_rule( int $event_id ): ?array {
		$raw = get_post_meta( $event_id, self::META_KEY, true );

		if ( empty( $raw ) ) {
			return null;
		}

		$rule = json_decode( $raw, true );

		if ( ! is_array( $rule ) ) {
			return null;
		}

		return $rule;
	}

	/**
	 * Delete the recurrence rule for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_rule( int $event_id ): bool {
		return delete_post_meta( $event_id, self::META_KEY );
	}

	/**
	 * Check whether an event has a recurrence rule.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return bool True if the event has a recurrence rule.
	 */
	public static function is_recurring( int $event_id ): bool {
		return null !== self::get_rule( $event_id );
	}

	/**
	 * Check whether an event is a generated instance (child of a template).
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return bool True if the event is a generated instance.
	 */
	public static function is_instance( int $event_id ): bool {
		return ! empty( get_post_meta( $event_id, self::TEMPLATE_META_KEY, true ) );
	}

	/**
	 * Get the template event ID for a generated instance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 * @return int The template event ID, or 0 if not an instance.
	 */
	public static function get_template_id( int $event_id ): int {
		return (int) get_post_meta( $event_id, self::TEMPLATE_META_KEY, true );
	}

	/**
	 * Calculate the next N occurrences for a recurrence rule.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $rule       The recurrence rule array.
	 * @param string $start_date Start date in Y-m-d format.
	 * @param int    $count      Number of occurrences to generate. Default 10.
	 * @return DateTime[] Array of DateTime objects for each occurrence.
	 */
	public static function calculate_occurrences( array $rule, string $start_date, int $count = 10 ): array {
		$frequency = $rule['frequency'] ?? '';
		$end_date  = isset( $rule['end_date'] ) ? new DateTime( $rule['end_date'] ) : null;

		$occurrences = array();
		$current     = new DateTime( $start_date );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( $end_date && $current > $end_date ) {
				break;
			}

			$occurrences[] = clone $current;

			switch ( $frequency ) {
				case 'weekly':
					$current->modify( '+1 week' );
					break;

				case 'biweekly':
					$current->modify( '+2 weeks' );
					break;

				case 'monthly-day':
					$current = self::next_monthly_day( $current, (int) ( $rule['day_of_week'] ?? 0 ) );
					break;

				case 'monthly-date':
					$current = self::next_monthly_date( $current, (int) ( $rule['day_of_month'] ?? 1 ) );
					break;
			}
		}

		return $occurrences;
	}

	/**
	 * Get a human-readable description of the recurrence pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule The recurrence rule array.
	 * @return string Human-readable description.
	 */
	public static function get_description( array $rule ): string {
		$frequency = $rule['frequency'] ?? '';
		$day_names = array(
			__( 'Sunday', 'gatherpress' ),
			__( 'Monday', 'gatherpress' ),
			__( 'Tuesday', 'gatherpress' ),
			__( 'Wednesday', 'gatherpress' ),
			__( 'Thursday', 'gatherpress' ),
			__( 'Friday', 'gatherpress' ),
			__( 'Saturday', 'gatherpress' ),
		);

		switch ( $frequency ) {
			case 'weekly':
				$day = $day_names[ $rule['day_of_week'] ?? 0 ] ?? '';
				/* translators: %s: day of the week (e.g., "Tuesday"). */
				return sprintf( __( 'Every %s', 'gatherpress' ), $day );

			case 'biweekly':
				$day = $day_names[ $rule['day_of_week'] ?? 0 ] ?? '';
				/* translators: %s: day of the week (e.g., "Tuesday"). */
				return sprintf( __( 'Every other %s', 'gatherpress' ), $day );

			case 'monthly-day':
				$day     = $day_names[ $rule['day_of_week'] ?? 0 ] ?? '';
				$ordinal = self::get_ordinal_for_day( new DateTime(), (int) ( $rule['day_of_week'] ?? 0 ) );
				/* translators: 1: ordinal (e.g., "first"), 2: day of the week (e.g., "Tuesday"). */
				return sprintf( __( '%1$s %2$s of every month', 'gatherpress' ), $ordinal, $day );

			case 'monthly-date':
				$day_of_month = $rule['day_of_month'] ?? 1;
				$suffix       = date_i18n( 'S', mktime( 0, 0, 0, 1, (int) $day_of_month ) );
				/* translators: %s: day of the month with ordinal suffix (e.g., "15th"). */
				return sprintf( __( '%s of every month', 'gatherpress' ), $day_of_month . $suffix );

			default:
				return '';
		}
	}

	/**
	 * Validate a recurrence rule array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule The rule to validate.
	 * @return array|WP_Error The sanitized rule on success, WP_Error on failure.
	 */
	private static function validate_rule( array $rule ) {
		if ( empty( $rule['frequency'] ) || ! in_array( $rule['frequency'], self::VALID_FREQUENCIES, true ) ) {
			return new WP_Error(
				'invalid_frequency',
				__( 'Recurrence frequency must be one of: weekly, biweekly, monthly-day, monthly-date.', 'gatherpress' )
			);
		}

		$sanitized = array(
			'frequency' => $rule['frequency'],
		);

		// Validate day_of_week for frequencies that need it.
		if ( in_array( $rule['frequency'], array( 'weekly', 'biweekly', 'monthly-day' ), true ) ) {
			if ( ! isset( $rule['day_of_week'] ) || ! is_numeric( $rule['day_of_week'] ) ) {
				return new WP_Error(
					'missing_day_of_week',
					__( 'day_of_week is required for this frequency.', 'gatherpress' )
				);
			}

			$day_of_week = (int) $rule['day_of_week'];

			if ( 0 > $day_of_week || 6 < $day_of_week ) {
				return new WP_Error(
					'invalid_day_of_week',
					__( 'day_of_week must be between 0 (Sunday) and 6 (Saturday).', 'gatherpress' )
				);
			}

			$sanitized['day_of_week'] = $day_of_week;
		}

		// Validate day_of_month for monthly-date.
		if ( 'monthly-date' === $rule['frequency'] ) {
			if ( ! isset( $rule['day_of_month'] ) || ! is_numeric( $rule['day_of_month'] ) ) {
				return new WP_Error(
					'missing_day_of_month',
					__( 'day_of_month is required for monthly-date frequency.', 'gatherpress' )
				);
			}

			$day_of_month = (int) $rule['day_of_month'];

			if ( 1 > $day_of_month || 31 < $day_of_month ) {
				return new WP_Error(
					'invalid_day_of_month',
					__( 'day_of_month must be between 1 and 31.', 'gatherpress' )
				);
			}

			$sanitized['day_of_month'] = $day_of_month;
		}

		// Validate optional end_date.
		if ( ! empty( $rule['end_date'] ) ) {
			$date = DateTime::createFromFormat( 'Y-m-d', $rule['end_date'] );

			if ( ! $date || $date->format( 'Y-m-d' ) !== $rule['end_date'] ) {
				return new WP_Error(
					'invalid_end_date',
					__( 'end_date must be a valid date in Y-m-d format.', 'gatherpress' )
				);
			}

			$sanitized['end_date'] = $rule['end_date'];
		}

		return $sanitized;
	}

	/**
	 * Calculate the next occurrence of a specific weekday-of-month pattern.
	 *
	 * Given a current date (e.g., the first Tuesday of April), find the same
	 * ordinal weekday in the next month (e.g., the first Tuesday of May).
	 *
	 * @since 1.0.0
	 *
	 * @param DateTime $current     The current occurrence date.
	 * @param int      $day_of_week Target day of week (0 = Sunday, 6 = Saturday).
	 * @return DateTime The next monthly-day occurrence.
	 */
	private static function next_monthly_day( DateTime $current, int $day_of_week ): DateTime {
		// Determine the ordinal week of the current date (1st, 2nd, 3rd, etc.).
		$day_number = (int) $current->format( 'j' );
		$ordinal    = (int) ceil( $day_number / 7 );

		$day_names = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$day_name  = $day_names[ $day_of_week ];

		// Move to next month.
		$next = clone $current;
		$next->modify( 'first day of next month' );

		// Find the Nth occurrence of the target weekday in that month.
		$ordinal_map = array(
			1 => 'first',
			2 => 'second',
			3 => 'third',
			4 => 'fourth',
			5 => 'fifth',
		);
		$ordinal_str = $ordinal_map[ $ordinal ] ?? 'fourth';

		$next->modify( "{$ordinal_str} {$day_name} of this month" );

		// If the calculated date is not in the expected month (e.g., "fifth Tuesday"
		// overflows), fall back to the fourth occurrence.
		$expected_month = (int) ( clone $current )->modify( 'first day of next month' )->format( 'n' );

		if ( (int) $next->format( 'n' ) !== $expected_month ) {
			$next = clone $current;
			$next->modify( 'first day of next month' );
			$next->modify( "fourth {$day_name} of this month" );
		}

		return $next;
	}

	/**
	 * Calculate the next monthly-date occurrence.
	 *
	 * If the target day doesn't exist in the next month (e.g., 31st in February),
	 * the last day of that month is used instead.
	 *
	 * @since 1.0.0
	 *
	 * @param DateTime $current       The current occurrence date.
	 * @param int      $day_of_month  Target day of month (1-31).
	 * @return DateTime The next monthly-date occurrence.
	 */
	private static function next_monthly_date( DateTime $current, int $day_of_month ): DateTime {
		$next = clone $current;
		$next->modify( 'first day of next month' );

		$days_in_month = (int) $next->format( 't' );
		$target_day    = min( $day_of_month, $days_in_month );

		$next->setDate(
			(int) $next->format( 'Y' ),
			(int) $next->format( 'n' ),
			$target_day
		);

		return $next;
	}

	/**
	 * Get the ordinal description for a day-of-week occurrence.
	 *
	 * @since 1.0.0
	 *
	 * @param DateTime $date        A reference date.
	 * @param int      $day_of_week Target day of week.
	 * @return string The ordinal string (e.g., "first", "second").
	 */
	private static function get_ordinal_for_day( DateTime $date, int $day_of_week ): string {
		$day_number = (int) $date->format( 'j' );
		$ordinal    = (int) ceil( $day_number / 7 );

		$ordinals = array(
			1 => __( 'First', 'gatherpress' ),
			2 => __( 'Second', 'gatherpress' ),
			3 => __( 'Third', 'gatherpress' ),
			4 => __( 'Fourth', 'gatherpress' ),
			5 => __( 'Fifth', 'gatherpress' ),
		);

		return $ordinals[ $ordinal ] ?? $ordinals[1];
	}
}
