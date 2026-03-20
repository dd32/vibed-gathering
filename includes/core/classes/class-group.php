<?php
/**
 * Class responsible for representing and managing group instances.
 *
 * The Group class is responsible for creating and managing instances of groups within the GatherPress plugin.
 * It provides methods for working with group data, such as retrieving group details and managing membership.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Post;

/**
 * Class Group.
 *
 * Represents individual groups/chapters within the GatherPress plugin and provides group-related functionality.
 *
 * @since 1.0.0
 */
class Group {
	/**
	 * The post type name for GatherPress groups.
	 *
	 * @since 1.0.0
	 * @var string $POST_TYPE
	 */
	const POST_TYPE = 'gatherpress_group';

	/**
	 * The taxonomy name for associating events with groups.
	 *
	 * @since 1.0.0
	 * @var string $TAXONOMY
	 */
	const TAXONOMY = '_gatherpress_group';

	/**
	 * Custom table name format for group membership.
	 *
	 * @since 1.0.0
	 * @var string $MEMBERSHIP_TABLE
	 */
	const MEMBERSHIP_TABLE = '%sgatherpress_group_members';

	/**
	 * Group membership role: Organizer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_ORGANIZER = 'organizer';

	/**
	 * Group membership role: Co-Organizer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_CO_ORGANIZER = 'co-organizer';

	/**
	 * Group membership role: Member.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_MEMBER = 'member';

	/**
	 * Group status: Active.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * Group status: Inactive.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_INACTIVE = 'inactive';

	/**
	 * Group status: Pending approval.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * The group post object.
	 *
	 * @since 1.0.0
	 * @var WP_Post|null
	 */
	protected ?WP_Post $group = null;

	/**
	 * The group post ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected int $group_id = 0;

	/**
	 * Constructor for the Group class.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID of the group.
	 */
	public function __construct( int $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$this->group    = get_post( $post_id );
			$this->group_id = $post_id;
		}
	}

	/**
	 * Check if the group instance is valid.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the group instance is valid, false otherwise.
	 */
	public function is_valid(): bool {
		return $this->group instanceof WP_Post;
	}

	/**
	 * Get the group post object.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Post|null The group post object, or null if invalid.
	 */
	public function get_post(): ?WP_Post {
		return $this->group;
	}

	/**
	 * Get the group post ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int The group post ID.
	 */
	public function get_id(): int {
		return $this->group_id;
	}

	/**
	 * Get the group location data.
	 *
	 * @since 1.0.0
	 *
	 * @return array{city: string, country: string, latitude: float, longitude: float} The group location data.
	 */
	public function get_location(): array {
		$default = array(
			'city'      => '',
			'country'   => '',
			'latitude'  => 0.0,
			'longitude' => 0.0,
		);

		$location = get_post_meta( $this->group_id, 'gatherpress_group_location', true );

		if ( empty( $location ) ) {
			return $default;
		}

		$decoded = json_decode( $location, true );

		if ( ! is_array( $decoded ) ) {
			return $default;
		}

		return wp_parse_args( $decoded, $default );
	}

	/**
	 * Get the group type (in-person, online, hybrid).
	 *
	 * @since 1.0.0
	 *
	 * @return string The group type.
	 */
	public function get_type(): string {
		$type = get_post_meta( $this->group_id, 'gatherpress_group_type', true );

		return ! empty( $type ) ? $type : 'in-person';
	}

	/**
	 * Get the member count for this group.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of members.
	 */
	public function get_member_count(): int {
		global $wpdb;

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE group_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				$table,
				$this->group_id,
				'active'
			)
		);

		return (int) $count;
	}

	/**
	 * Get the members of this group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Optional. Filter by role. Default empty (all roles).
	 * @param int    $limit Optional. Number of members to return. Default 50.
	 * @param int    $offset Optional. Offset for pagination. Default 0.
	 * @return array<int, object> Array of member objects.
	 */
	public function get_members( string $role = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		if ( ! empty( $role ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE group_id = %d AND status = %s AND role = %s ORDER BY joined_at DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
					$table,
					$this->group_id,
					'active',
					$role,
					$limit,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE group_id = %d AND status = %s ORDER BY joined_at DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
					$table,
					$this->group_id,
					'active',
					$limit,
					$offset
				)
			);
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Check if a user is a member of this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool True if the user is a member, false otherwise.
	 */
	public function is_member( int $user_id ): bool {
		global $wpdb;

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE group_id = %d AND user_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				$table,
				$this->group_id,
				$user_id,
				'active'
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get a user's role in this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to check.
	 * @return string|null The user's role, or null if not a member.
	 */
	public function get_member_role( int $user_id ): ?string {
		global $wpdb;

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$role = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT role FROM %i WHERE group_id = %d AND user_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				$table,
				$this->group_id,
				$user_id,
				'active'
			)
		);

		return $role ? (string) $role : null;
	}

	/**
	 * Add a member to this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID to add.
	 * @param string $role The role to assign. Default 'member'.
	 * @return bool True on success, false on failure.
	 */
	public function add_member( int $user_id, string $role = self::ROLE_MEMBER ): bool {
		global $wpdb;

		if ( $this->is_member( $user_id ) ) {
			return false;
		}

		$valid_roles = array( self::ROLE_ORGANIZER, self::ROLE_CO_ORGANIZER, self::ROLE_MEMBER );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			return false;
		}

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'group_id'  => $this->group_id,
				'user_id'   => $user_id,
				'role'      => $role,
				'status'    => 'active',
				'joined_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false !== $result ) {
			wp_cache_delete( 'group_member_count_' . $this->group_id, GATHERPRESS_CACHE_GROUP );

			/**
			 * Fires after a user joins a group.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $group_id The group post ID.
			 * @param int    $user_id  The user ID.
			 * @param string $role     The membership role.
			 */
			do_action( 'gatherpress_group_member_added', $this->group_id, $user_id, $role );
		}

		return false !== $result;
	}

	/**
	 * Remove a member from this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to remove.
	 * @return bool True on success, false on failure.
	 */
	public function remove_member( int $user_id ): bool {
		global $wpdb;

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'status' => 'inactive' ),
			array(
				'group_id' => $this->group_id,
				'user_id'  => $user_id,
				'status'   => 'active',
			),
			array( '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $result && $result > 0 ) {
			wp_cache_delete( 'group_member_count_' . $this->group_id, GATHERPRESS_CACHE_GROUP );

			/**
			 * Fires after a user leaves a group.
			 *
			 * @since 1.0.0
			 *
			 * @param int $group_id The group post ID.
			 * @param int $user_id  The user ID.
			 */
			do_action( 'gatherpress_group_member_removed', $this->group_id, $user_id );
		}

		return false !== $result && $result > 0;
	}

	/**
	 * Update a member's role in this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID to update.
	 * @param string $role The new role to assign.
	 * @return bool True on success, false on failure.
	 */
	public function update_member_role( int $user_id, string $role ): bool {
		global $wpdb;

		$valid_roles = array( self::ROLE_ORGANIZER, self::ROLE_CO_ORGANIZER, self::ROLE_MEMBER );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			return false;
		}

		$table = sprintf( self::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'role' => $role ),
			array(
				'group_id' => $this->group_id,
				'user_id'  => $user_id,
				'status'   => 'active',
			),
			array( '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false !== $result && $result > 0 ) {
			/**
			 * Fires after a member's role is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $group_id The group post ID.
			 * @param int    $user_id  The user ID.
			 * @param string $role     The new role.
			 */
			do_action( 'gatherpress_group_member_role_updated', $this->group_id, $user_id, $role );
		}

		return false !== $result && $result > 0;
	}

	/**
	 * Get the upcoming events for this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Optional. Number of events to return. Default 5.
	 * @return array<int, WP_Post> Array of event posts.
	 */
	public function get_upcoming_events( int $limit = 5 ): array {
		$term = get_term_by( 'slug', $this->get_group_term_slug(), self::TAXONOMY );

		if ( ! is_a( $term, '\WP_Term' ) ) {
			return array();
		}

		$args = array(
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'gatherpress_datetime_end',
					'value'   => gmdate( Event::DATETIME_FORMAT ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'gatherpress_datetime_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get the group taxonomy term slug.
	 *
	 * Generates a prefixed slug for the group's taxonomy term,
	 * following the same pattern as venues.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Optional. The post slug. Defaults to the group's post_name.
	 * @return string The taxonomy term slug.
	 */
	public function get_group_term_slug( string $slug = '' ): string {
		if ( empty( $slug ) && $this->group instanceof WP_Post ) {
			$slug = $this->group->post_name;
		}

		return '_group_' . $slug;
	}

	/**
	 * Get all valid group roles.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Associative array of role slug => label.
	 */
	public static function get_roles(): array {
		return array(
			self::ROLE_ORGANIZER    => __( 'Organizer', 'gatherpress' ),
			self::ROLE_CO_ORGANIZER => __( 'Co-Organizer', 'gatherpress' ),
			self::ROLE_MEMBER       => __( 'Member', 'gatherpress' ),
		);
	}
}
