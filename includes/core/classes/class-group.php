<?php
/**
 * Class responsible for representing and managing group instances.
 *
 * In the multisite architecture, each group is a WordPress site (blog).
 * The Group class wraps a blog_id and provides methods for membership
 * management, event queries, and group metadata.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_User;

/**
 * Class Group.
 *
 * Represents a group (WordPress multisite site) within the GatherPress plugin.
 * Each group is a site in the network, with membership managed via native
 * WordPress multisite user roles.
 *
 * @since 1.0.0
 */
class Group {
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
	const ROLE_CO_ORGANIZER = 'co_organizer';

	/**
	 * Group membership role: Member.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROLE_MEMBER = 'member';

	/**
	 * Blog option key for group status.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_STATUS = 'gatherpress_group_status';

	/**
	 * Blog option key for group location (JSON).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_LOCATION = 'gatherpress_group_location';

	/**
	 * Blog option key for group type (in-person, online, hybrid).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_TYPE = 'gatherpress_group_type';

	/**
	 * Blog option key for group links (JSON).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_LINKS = 'gatherpress_group_links';

	/**
	 * The blog ID representing this group.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected int $blog_id;

	/**
	 * Constructor for the Group class.
	 *
	 * @since 1.0.0
	 *
	 * @param int $blog_id The blog ID of the group site. Defaults to current blog.
	 */
	public function __construct( int $blog_id = 0 ) {
		$this->blog_id = $blog_id ? $blog_id : get_current_blog_id();
	}

	/**
	 * Check if the group instance is valid.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the group site exists, false otherwise.
	 */
	public function is_valid(): bool {
		$blog = get_blog_details( $this->blog_id );

		return ! empty( $blog ) && ! $blog->deleted && ! $blog->archived;
	}

	/**
	 * Get the blog ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int The blog ID.
	 */
	public function get_blog_id(): int {
		return $this->blog_id;
	}

	/**
	 * Get the group name (blog title).
	 *
	 * @since 1.0.0
	 *
	 * @return string The group name.
	 */
	public function get_name(): string {
		return get_blog_option( $this->blog_id, 'blogname', '' );
	}

	/**
	 * Get the group description (blog tagline).
	 *
	 * @since 1.0.0
	 *
	 * @return string The group description.
	 */
	public function get_description(): string {
		return get_blog_option( $this->blog_id, 'blogdescription', '' );
	}

	/**
	 * Get the group URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The group site URL.
	 */
	public function get_url(): string {
		return get_blog_option( $this->blog_id, 'siteurl', '' );
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

		$location = get_blog_option( $this->blog_id, self::OPTION_LOCATION, '' );

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
	 * Set the group location data.
	 *
	 * @since 1.0.0
	 *
	 * @param array{city?: string, country?: string, latitude?: float, longitude?: float} $location The location data.
	 * @return bool True on success, false on failure.
	 */
	public function set_location( array $location ): bool {
		$current = $this->get_location();
		$merged  = wp_parse_args( $location, $current );

		return update_blog_option(
			$this->blog_id,
			self::OPTION_LOCATION,
			wp_json_encode( $merged )
		);
	}

	/**
	 * Get the group type (in-person, online, hybrid).
	 *
	 * @since 1.0.0
	 *
	 * @return string The group type.
	 */
	public function get_type(): string {
		$type = get_blog_option( $this->blog_id, self::OPTION_TYPE, 'in-person' );

		return ! empty( $type ) ? $type : 'in-person';
	}

	/**
	 * Get the group status (active, inactive, pending).
	 *
	 * @since 1.0.0
	 *
	 * @return string The group status.
	 */
	public function get_status(): string {
		return get_blog_option( $this->blog_id, self::OPTION_STATUS, 'active' );
	}

	/**
	 * Get the member count for this group.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of members.
	 */
	public function get_member_count(): int {
		$count = wp_cache_get( 'member_count_' . $this->blog_id, GATHERPRESS_CACHE_GROUP );

		if ( false !== $count ) {
			return (int) $count;
		}

		$users = get_users(
			array(
				'blog_id' => $this->blog_id,
				'fields'  => 'ID',
			)
		);

		$count = count( $users );
		wp_cache_set( 'member_count_' . $this->blog_id, $count, GATHERPRESS_CACHE_GROUP );

		return $count;
	}

	/**
	 * Get the members of this group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Optional. Filter by role. Default empty (all roles).
	 * @param int    $number Optional. Number of members to return. Default 50.
	 * @param int    $offset Optional. Offset for pagination. Default 0.
	 * @return WP_User[] Array of WP_User objects.
	 */
	public function get_members( string $role = '', int $number = 50, int $offset = 0 ): array {
		$args = array(
			'blog_id' => $this->blog_id,
			'number'  => $number,
			'offset'  => $offset,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);

		if ( ! empty( $role ) ) {
			$args['role'] = $role;
		}

		return get_users( $args );
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
		return is_user_member_of_blog( $user_id, $this->blog_id );
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
		if ( ! $this->is_member( $user_id ) ) {
			return null;
		}

		switch_to_blog( $this->blog_id );
		$user  = new WP_User( $user_id );
		$roles = $user->roles;
		restore_current_blog();

		if ( empty( $roles ) ) {
			return null;
		}

		return reset( $roles );
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
		if ( $this->is_member( $user_id ) ) {
			return false;
		}

		if ( $this->is_banned( $user_id ) ) {
			return false;
		}

		$result = add_user_to_blog( $this->blog_id, $user_id, $role );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Record when the user joined this group.
		update_user_meta( $user_id, '_gatherpress_joined_' . $this->blog_id, current_time( 'mysql', true ) );

		wp_cache_delete( 'member_count_' . $this->blog_id, GATHERPRESS_CACHE_GROUP );

		/**
		 * Fires after a user joins a group.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $blog_id The blog ID of the group.
		 * @param int    $user_id The user ID.
		 * @param string $role    The membership role.
		 */
		do_action( 'gatherpress_group_member_added', $this->blog_id, $user_id, $role );

		return true;
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
		$result = remove_user_from_blog( $user_id, $this->blog_id );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		delete_user_meta( $user_id, '_gatherpress_joined_' . $this->blog_id );
		wp_cache_delete( 'member_count_' . $this->blog_id, GATHERPRESS_CACHE_GROUP );

		/**
		 * Fires after a user leaves a group.
		 *
		 * @since 1.0.0
		 *
		 * @param int $blog_id The blog ID of the group.
		 * @param int $user_id The user ID.
		 */
		do_action( 'gatherpress_group_member_removed', $this->blog_id, $user_id );

		return true;
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
		$valid_roles = array_keys( self::get_roles() );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			return false;
		}

		if ( ! $this->is_member( $user_id ) ) {
			return false;
		}

		switch_to_blog( $this->blog_id );
		$user = new WP_User( $user_id );
		$user->set_role( $role );
		restore_current_blog();

		/**
		 * Fires after a member's role is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $blog_id The blog ID of the group.
		 * @param int    $user_id The user ID.
		 * @param string $role    The new role.
		 */
		do_action( 'gatherpress_group_member_role_updated', $this->blog_id, $user_id, $role );

		return true;
	}

	/**
	 * Ban a user from this group.
	 *
	 * Removes the user and sets a meta flag to prevent rejoin.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to ban.
	 * @return bool True on success, false on failure.
	 */
	public function ban_member( int $user_id ): bool {
		if ( $this->is_member( $user_id ) ) {
			$this->remove_member( $user_id );
		}

		update_user_meta( $user_id, '_gatherpress_banned_' . $this->blog_id, 1 );

		/**
		 * Fires after a user is banned from a group.
		 *
		 * @since 1.0.0
		 *
		 * @param int $blog_id The blog ID of the group.
		 * @param int $user_id The user ID.
		 */
		do_action( 'gatherpress_group_member_banned', $this->blog_id, $user_id );

		return true;
	}

	/**
	 * Unban a user from this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to unban.
	 * @return bool True on success.
	 */
	public function unban_member( int $user_id ): bool {
		delete_user_meta( $user_id, '_gatherpress_banned_' . $this->blog_id );

		return true;
	}

	/**
	 * Check if a user is banned from this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool True if the user is banned, false otherwise.
	 */
	public function is_banned( int $user_id ): bool {
		return (bool) get_user_meta( $user_id, '_gatherpress_banned_' . $this->blog_id, true );
	}

	/**
	 * Check if a user can manage members in this group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool True if the user can manage members.
	 */
	public function can_manage_members( int $user_id ): bool {
		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		return self::ROLE_ORGANIZER === $this->get_member_role( $user_id );
	}

	/**
	 * Get the upcoming events for this group.
	 *
	 * Queries the group's site for upcoming GatherPress events.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Optional. Number of events to return. Default 5.
	 * @return array<int, array{id: int, title: string, permalink: string}> Array of event data.
	 */
	public function get_upcoming_events( int $limit = 5 ): array {
		switch_to_blog( $this->blog_id );

		$args = array(
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
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

		$query  = new \WP_Query( $args );
		$events = array();

		foreach ( $query->posts as $post ) {
			$events[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post->ID ),
				'permalink' => get_permalink( $post->ID ),
			);
		}

		restore_current_blog();

		return $events;
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

	/**
	 * Get all group sites in the network.
	 *
	 * Returns sites that have GatherPress activated and are marked as group sites.
	 * The main site (blog_id 1) is excluded.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional. Arguments for get_sites(). Default empty.
	 * @return Group[] Array of Group objects.
	 */
	public static function get_all_groups( array $args = array() ): array {
		$defaults = array(
			'fields'       => 'ids',
			'network_id'   => get_current_network_id(),
			'site__not_in' => array( get_main_site_id() ),
			'public'       => 1,
			'archived'     => 0,
			'deleted'      => 0,
			'number'       => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		// Cache the site query results for 5 minutes to avoid repeated DB queries.
		$gatherpress_cache_key = 'gatherpress_all_groups_' . md5( wp_json_encode( $args ) );
		$gatherpress_cached    = wp_cache_get( $gatherpress_cache_key, GATHERPRESS_CACHE_GROUP );

		if ( is_array( $gatherpress_cached ) ) {
			return $gatherpress_cached;
		}

		$site_ids = get_sites( $args );
		$groups   = array();

		foreach ( $site_ids as $site_id ) {
			$group = new self( (int) $site_id );
			if ( $group->is_valid() ) {
				$groups[] = $group;
			}
		}

		wp_cache_set( $gatherpress_cache_key, $groups, GATHERPRESS_CACHE_GROUP, 300 );

		return $groups;
	}

	/**
	 * Get the groups a user belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @return Group[] Array of Group objects.
	 */
	public static function get_user_groups( int $user_id ): array {
		$blogs  = get_blogs_of_user( $user_id );
		$groups = array();

		foreach ( $blogs as $blog ) {
			// Skip the main site.
			if ( get_main_site_id() === (int) $blog->userblog_id ) {
				continue;
			}

			$group = new self( (int) $blog->userblog_id );
			if ( $group->is_valid() ) {
				$groups[] = $group;
			}
		}

		return $groups;
	}
}
