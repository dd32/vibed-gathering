<?php
/**
 * Activity logging for GatherPress.
 *
 * Records key user actions across the platform for audit trails
 * and analytics. Stores logs in a custom database table.
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

/**
 * Class Activity_Log.
 *
 * Records and queries platform activity for analytics and auditing.
 *
 * @since 1.0.0
 */
class Activity_Log {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Custom table name format.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TABLE_FORMAT = '%sgatherpress_activity_log';

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

		// Log key platform events.
		add_action( 'gatherpress_group_member_added', array( $this, 'log_member_joined' ), 10, 3 );
		add_action( 'gatherpress_group_member_removed', array( $this, 'log_member_left' ), 10, 2 );
		add_action( 'gatherpress_event_cancelled', array( $this, 'log_event_cancelled' ), 10, 2 );
		add_action( 'gatherpress_attendee_checked_in', array( $this, 'log_checkin' ), 10, 2 );
		add_action( 'gatherpress_feedback_submitted', array( $this, 'log_feedback' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'log_event_published' ), 10, 3 );
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/activity-log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_activity' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'per_page' => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'action'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/activity-log/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_csv' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Log an activity entry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gatherpress_action  The action name (e.g., 'member_joined').
	 * @param int    $gatherpress_user_id The user who performed the action.
	 * @param int    $gatherpress_obj_id  The related object ID (post, user, etc.).
	 * @param string $gatherpress_details Additional details (JSON or text).
	 * @return bool True on success, false on failure.
	 */
	public static function log(
		string $gatherpress_action,
		int $gatherpress_user_id = 0,
		int $gatherpress_obj_id = 0,
		string $gatherpress_details = ''
	): bool {
		global $wpdb;

		if ( empty( $gatherpress_user_id ) ) {
			$gatherpress_user_id = get_current_user_id();
		}

		$gatherpress_table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$gatherpress_result = $wpdb->insert(
			$gatherpress_table,
			array(
				'blog_id'    => get_current_blog_id(),
				'user_id'    => $gatherpress_user_id,
				'action'     => $gatherpress_action,
				'object_id'  => $gatherpress_obj_id,
				'details'    => $gatherpress_details,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return false !== $gatherpress_result;
	}

	/**
	 * Log when a member joins a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $gatherpress_blog_id The blog ID.
	 * @param int    $gatherpress_user_id The user ID.
	 * @param string $gatherpress_role    The role.
	 * @return void
	 */
	public function log_member_joined( int $gatherpress_blog_id, int $gatherpress_user_id, string $gatherpress_role ): void {
		self::log( 'member_joined', $gatherpress_user_id, $gatherpress_blog_id, $gatherpress_role );
	}

	/**
	 * Log when a member leaves a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_blog_id The blog ID.
	 * @param int $gatherpress_user_id The user ID.
	 * @return void
	 */
	public function log_member_left( int $gatherpress_blog_id, int $gatherpress_user_id ): void {
		self::log( 'member_left', $gatherpress_user_id, $gatherpress_blog_id );
	}

	/**
	 * Log when an event is cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $gatherpress_event_id The event ID.
	 * @param string $gatherpress_reason   The reason.
	 * @return void
	 */
	public function log_event_cancelled( int $gatherpress_event_id, string $gatherpress_reason ): void {
		self::log( 'event_cancelled', get_current_user_id(), $gatherpress_event_id, $gatherpress_reason );
	}

	/**
	 * Log when an attendee checks in.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event ID.
	 * @param int $gatherpress_user_id  The user ID.
	 * @return void
	 */
	public function log_checkin( int $gatherpress_event_id, int $gatherpress_user_id ): void {
		self::log( 'attendee_checked_in', $gatherpress_user_id, $gatherpress_event_id );
	}

	/**
	 * Log when feedback is submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $gatherpress_event_id The event ID.
	 * @param int $gatherpress_user_id  The user ID.
	 * @param int $gatherpress_rating   The rating.
	 * @return void
	 */
	public function log_feedback( int $gatherpress_event_id, int $gatherpress_user_id, int $gatherpress_rating ): void {
		self::log( 'feedback_submitted', $gatherpress_user_id, $gatherpress_event_id, (string) $gatherpress_rating );
	}

	/**
	 * Log when an event is published.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $gatherpress_new  New status.
	 * @param string   $gatherpress_old  Old status.
	 * @param \WP_Post $gatherpress_post The post.
	 * @return void
	 */
	public function log_event_published( string $gatherpress_new, string $gatherpress_old, \WP_Post $gatherpress_post ): void {
		if ( Event::POST_TYPE !== $gatherpress_post->post_type || 'publish' !== $gatherpress_new || 'publish' === $gatherpress_old ) {
			return;
		}
		self::log( 'event_published', (int) $gatherpress_post->post_author, $gatherpress_post->ID );
	}

	/**
	 * Get activity log entries via REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response The response.
	 */
	public function get_activity( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$gatherpress_per_page = min( intval( $request->get_param( 'per_page' ) ), 100 );
		$gatherpress_page     = intval( $request->get_param( 'page' ) );
		$gatherpress_offset   = ( $gatherpress_page - 1 ) * $gatherpress_per_page;
		$gatherpress_action   = $request->get_param( 'action' ) ?? '';
		$gatherpress_table    = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		if ( ! empty( $gatherpress_action ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$gatherpress_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE action = %s ORDER BY created_at DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
					$gatherpress_table,
					$gatherpress_action,
					$gatherpress_per_page,
					$gatherpress_offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$gatherpress_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
					$gatherpress_table,
					$gatherpress_per_page,
					$gatherpress_offset
				)
			);
		}

		$gatherpress_entries = array();
		if ( is_array( $gatherpress_rows ) ) {
			foreach ( $gatherpress_rows as $gatherpress_row ) {
				$gatherpress_user = get_user_by( 'id', (int) $gatherpress_row->user_id );
				$gatherpress_entries[] = array(
					'id'         => (int) $gatherpress_row->id,
					'action'     => $gatherpress_row->action,
					'user_id'    => (int) $gatherpress_row->user_id,
					'user_name'  => $gatherpress_user ? $gatherpress_user->display_name : '',
					'object_id'  => (int) $gatherpress_row->object_id,
					'details'    => $gatherpress_row->details,
					'created_at' => $gatherpress_row->created_at,
				);
			}
		}

		return new WP_REST_Response( array( 'entries' => $gatherpress_entries ) );
	}

	/**
	 * Export activity log as CSV.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 * @return void
	 */
	public function export_csv( WP_REST_Request $request ): void {
		global $wpdb;

		$gatherpress_table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$gatherpress_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				$gatherpress_table,
				10000
			)
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gatherpress-activity-log.csv"' );

		$gatherpress_output = fopen( 'php://output', 'w' );
		fputcsv( $gatherpress_output, array( 'ID', 'Action', 'User ID', 'User Name', 'Object ID', 'Details', 'Date' ) );

		if ( is_array( $gatherpress_rows ) ) {
			foreach ( $gatherpress_rows as $gatherpress_row ) {
				$gatherpress_user = get_user_by( 'id', (int) $gatherpress_row->user_id );
				fputcsv(
					$gatherpress_output,
					array(
						$gatherpress_row->id,
						$gatherpress_row->action,
						$gatherpress_row->user_id,
						$gatherpress_user ? $gatherpress_user->display_name : '',
						$gatherpress_row->object_id,
						$gatherpress_row->details,
						$gatherpress_row->created_at,
					)
				);
			}
		}

		fclose( $gatherpress_output );
		Utility::safe_exit();
	}

	/**
	 * Create the activity log database table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$gatherpress_table   = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$gatherpress_charset = $wpdb->get_charset_collate();

		$gatherpress_sql = "CREATE TABLE {$gatherpress_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY blog_action (blog_id, action),
			KEY created_at (created_at)
		) {$gatherpress_charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $gatherpress_sql );
	}
}
