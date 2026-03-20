<?php
/**
 * Handles the registration of Group REST API endpoints.
 *
 * This file contains the Group_Rest_Api class, which is responsible for registering and managing
 * various Group REST API endpoints within the GatherPress plugin.
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
use WP_Error;

/**
 * Class Group_Rest_Api.
 *
 * Manages REST API endpoints for group-related functionality including membership,
 * group events, and group discovery.
 *
 * @since 1.0.0
 */
class Group_Rest_Api {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
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
		add_filter( sprintf( 'rest_prepare_%s', Group::POST_TYPE ), array( $this, 'prepare_group_data' ) );
	}

	/**
	 * Registers REST API endpoints for GatherPress groups.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		$routes = $this->get_group_routes();

		foreach ( $routes as $route ) {
			register_rest_route(
				sprintf( '%s/group', GATHERPRESS_REST_NAMESPACE ),
				sprintf( '/%s', $route['route'] ),
				$route['args']
			);
		}
	}

	/**
	 * Get the group routes.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] An array of route definitions for GatherPress groups.
	 */
	protected function get_group_routes(): array {
		return array(
			$this->join_route(),
			$this->leave_route(),
			$this->members_route(),
			$this->update_member_route(),
			$this->events_route(),
			$this->user_groups_route(),
		);
	}

	/**
	 * Define the REST route for joining a group.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function join_route(): array {
		return array(
			'route' => 'join',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'join_group' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group_post_id' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for leaving a group.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function leave_route(): array {
		return array(
			'route' => 'leave',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'leave_group' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group_post_id' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for listing group members.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function members_route(): array {
		return array(
			'route' => 'members',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_members' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'  => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group_post_id' ),
					),
					'role'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for updating a member's role.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function update_member_route(): array {
		return array(
			'route' => 'member',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_member' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group_post_id' ),
					),
					'user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'role'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for listing group events.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function events_route(): array {
		return array(
			'route' => 'events',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_events' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group_post_id' ),
					),
					'limit'   => array(
						'required'          => false,
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for listing the current user's groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function user_groups_route(): array {
		return array(
			'route' => 'user-groups',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_groups' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
			),
		);
	}

	/**
	 * Validate that a post ID belongs to a group post type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_group_post_id( $value ): bool {
		return is_numeric( $value ) && Group::POST_TYPE === get_post_type( intval( $value ) );
	}

	/**
	 * Handle the join group request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function join_group( WP_REST_Request $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$user_id = get_current_user_id();
		$group   = new Group( $post_id );

		if ( ! $group->is_valid() ) {
			return new WP_Error(
				'invalid_group',
				__( 'Invalid group.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		if ( $group->is_member( $user_id ) ) {
			return new WP_Error(
				'already_member',
				__( 'You are already a member of this group.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$result = $group->add_member( $user_id );

		if ( ! $result ) {
			return new WP_Error(
				'join_failed',
				__( 'Failed to join group.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'member_count' => $group->get_member_count(),
			)
		);
	}

	/**
	 * Handle the leave group request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function leave_group( WP_REST_Request $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$user_id = get_current_user_id();
		$group   = new Group( $post_id );

		if ( ! $group->is_valid() ) {
			return new WP_Error(
				'invalid_group',
				__( 'Invalid group.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		// Prevent the last organizer from leaving.
		$role       = $group->get_member_role( $user_id );
		$organizers = $group->get_members( Group::ROLE_ORGANIZER );

		if ( Group::ROLE_ORGANIZER === $role && count( $organizers ) <= 1 ) {
			return new WP_Error(
				'last_organizer',
				__( 'You are the last organizer. Please assign another organizer before leaving.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$result = $group->remove_member( $user_id );

		if ( ! $result ) {
			return new WP_Error(
				'leave_failed',
				__( 'Failed to leave group.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'member_count' => $group->get_member_count(),
			)
		);
	}

	/**
	 * Handle the get members request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function get_members( WP_REST_Request $request ): WP_REST_Response {
		$post_id  = intval( $request->get_param( 'post_id' ) );
		$role     = $request->get_param( 'role' ) ?? '';
		$page     = intval( $request->get_param( 'page' ) );
		$per_page = min( intval( $request->get_param( 'per_page' ) ), 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$group   = new Group( $post_id );
		$members = $group->get_members( $role, $per_page, $offset );

		$data = array_map(
			static function ( $member ) {
				$user = get_user_by( 'id', $member->user_id );
				return array(
					'user_id'    => (int) $member->user_id,
					'role'       => $member->role,
					'joined_at'  => $member->joined_at,
					'name'       => $user ? $user->display_name : '',
					'avatar_url' => get_avatar_url( (int) $member->user_id, array( 'size' => 96 ) ),
				);
			},
			$members
		);

		return new WP_REST_Response(
			array(
				'members'      => $data,
				'member_count' => $group->get_member_count(),
			)
		);
	}

	/**
	 * Handle the update member request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function update_member( WP_REST_Request $request ) {
		$post_id      = intval( $request->get_param( 'post_id' ) );
		$target_user  = intval( $request->get_param( 'user_id' ) );
		$new_role     = $request->get_param( 'role' );
		$current_user = get_current_user_id();

		$group        = new Group( $post_id );
		$current_role = $group->get_member_role( $current_user );

		// Only organizers can update member roles.
		if ( Group::ROLE_ORGANIZER !== $current_role && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to manage members.', 'gatherpress' ),
				array( 'status' => 403 )
			);
		}

		$result = $group->update_member_role( $target_user, $new_role );

		if ( ! $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update member role.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array( 'success' => true )
		);
	}

	/**
	 * Handle the get events request for a group.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function get_events( WP_REST_Request $request ): WP_REST_Response {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$limit   = min( intval( $request->get_param( 'limit' ) ), 50 );

		$group  = new Group( $post_id );
		$events = $group->get_upcoming_events( $limit );

		$data = array_map(
			static function ( $event_post ) {
				$event = new Event( $event_post->ID );
				return array(
					'id'        => $event_post->ID,
					'title'     => get_the_title( $event_post->ID ),
					'permalink' => get_permalink( $event_post->ID ),
					'datetime'  => $event->get_display_datetime(),
					'excerpt'   => get_the_excerpt( $event_post ),
				);
			},
			$events
		);

		return new WP_REST_Response( array( 'events' => $data ) );
	}

	/**
	 * Handle the get user groups request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function get_user_groups( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$user_id = get_current_user_id();
		$table   = sprintf( Group::MEMBERSHIP_TABLE, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$memberships = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT group_id, role, joined_at FROM %i WHERE user_id = %d AND status = %s ORDER BY joined_at DESC', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				$table,
				$user_id,
				'active'
			)
		);

		$data = array();
		if ( is_array( $memberships ) ) {
			foreach ( $memberships as $membership ) {
				$group_post = get_post( (int) $membership->group_id );
				if ( $group_post && 'publish' === $group_post->post_status ) {
					$group  = new Group( (int) $membership->group_id );
					$data[] = array(
						'id'           => (int) $membership->group_id,
						'title'        => get_the_title( (int) $membership->group_id ),
						'permalink'    => get_permalink( (int) $membership->group_id ),
						'role'         => $membership->role,
						'joined_at'    => $membership->joined_at,
						'member_count' => $group->get_member_count(),
						'thumbnail'    => get_the_post_thumbnail_url( (int) $membership->group_id, 'medium' ),
					);
				}
			}
		}

		return new WP_REST_Response( array( 'groups' => $data ) );
	}

	/**
	 * Add group-specific data to group REST API responses.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Response $response The REST response object.
	 * @return WP_REST_Response Modified response with group data.
	 */
	public function prepare_group_data( WP_REST_Response $response ): WP_REST_Response {
		$data    = $response->get_data();
		$post_id = $data['id'] ?? 0;

		if ( ! $post_id ) {
			return $response;
		}

		$group = new Group( $post_id );

		if ( ! $group->is_valid() ) {
			return $response;
		}

		$data['gatherpress_group'] = array(
			'location'     => $group->get_location(),
			'type'         => $group->get_type(),
			'member_count' => $group->get_member_count(),
			'is_member'    => is_user_logged_in() ? $group->is_member( get_current_user_id() ) : false,
			'current_role' => is_user_logged_in() ? $group->get_member_role( get_current_user_id() ) : null,
		);

		$response->set_data( $data );

		return $response;
	}
}
