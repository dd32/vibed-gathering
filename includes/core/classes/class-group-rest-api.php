<?php
/**
 * Handles the registration of Group REST API endpoints.
 *
 * Provides REST API endpoints for group membership management, group discovery,
 * and group event queries in a WordPress multisite environment.
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
 * group events, and group discovery across a WordPress multisite network.
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
		// Group endpoints require multisite for meaningful behavior.
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
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
			$this->directory_route(),
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
					'blog_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
					'blog_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
					'blog_id'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
					'blog_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
					'blog_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
	 * Define the REST route for the group directory.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function directory_route(): array {
		return array(
			'route' => 'directory',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_directory' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			),
		);
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
		$blog_id = intval( $request->get_param( 'blog_id' ) );
		$user_id = get_current_user_id();
		$group   = new Group( $blog_id );

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
				__( 'Failed to join group. You may be banned from this group.', 'gatherpress' ),
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
		$blog_id = intval( $request->get_param( 'blog_id' ) );
		$user_id = get_current_user_id();
		$group   = new Group( $blog_id );

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
		$blog_id  = intval( $request->get_param( 'blog_id' ) );
		$role     = $request->get_param( 'role' ) ?? '';
		$page     = intval( $request->get_param( 'page' ) );
		$per_page = min( intval( $request->get_param( 'per_page' ) ), 100 );
		$offset   = ( $page - 1 ) * $per_page;

		$group   = new Group( $blog_id );
		$members = $group->get_members( $role, $per_page, $offset );

		$data = array_map(
			static function ( $user ) use ( $group ) {
				return array(
					'user_id'    => $user->ID,
					'name'       => $user->display_name,
					'role'       => $group->get_member_role( $user->ID ),
					'avatar_url' => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
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
		$blog_id      = intval( $request->get_param( 'blog_id' ) );
		$target_user  = intval( $request->get_param( 'user_id' ) );
		$new_role     = $request->get_param( 'role' );
		$current_user = get_current_user_id();

		$group = new Group( $blog_id );

		// Only organizers and super admins can update member roles.
		if ( ! $group->can_manage_members( $current_user ) ) {
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

		return new WP_REST_Response( array( 'success' => true ) );
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
		$blog_id = intval( $request->get_param( 'blog_id' ) );
		$limit   = min( intval( $request->get_param( 'limit' ) ), 50 );

		$group  = new Group( $blog_id );
		$events = $group->get_upcoming_events( $limit );

		return new WP_REST_Response( array( 'events' => $events ) );
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
		$user_id = get_current_user_id();
		$groups  = Group::get_user_groups( $user_id );

		$data = array_map(
			static function ( Group $group ) use ( $user_id ) {
				return array(
					'blog_id'      => $group->get_blog_id(),
					'name'         => $group->get_name(),
					'url'          => $group->get_url(),
					'description'  => $group->get_description(),
					'role'         => $group->get_member_role( $user_id ),
					'member_count' => $group->get_member_count(),
					'type'         => $group->get_type(),
					'location'     => $group->get_location(),
				);
			},
			$groups
		);

		return new WP_REST_Response( array( 'groups' => $data ) );
	}

	/**
	 * Handle the group directory request.
	 *
	 * Returns a paginated list of all groups in the network.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response.
	 */
	public function get_directory( WP_REST_Request $request ): WP_REST_Response {
		$search   = $request->get_param( 'search' ) ?? '';
		$per_page = min( intval( $request->get_param( 'per_page' ) ), 100 );
		$page     = intval( $request->get_param( 'page' ) );
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'number' => $per_page,
			'offset' => $offset,
		);

		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$groups  = Group::get_all_groups( $args );
		$user_id = get_current_user_id();

		$data = array_map(
			static function ( Group $group ) use ( $user_id ) {
				$upcoming = $group->get_upcoming_events( 1 );
				return array(
					'blog_id'      => $group->get_blog_id(),
					'name'         => $group->get_name(),
					'url'          => $group->get_url(),
					'description'  => $group->get_description(),
					'member_count' => $group->get_member_count(),
					'type'         => $group->get_type(),
					'location'     => $group->get_location(),
					'status'       => $group->get_status(),
					'is_member'    => $user_id ? $group->is_member( $user_id ) : false,
					'next_event'   => ! empty( $upcoming ) ? $upcoming[0] : null,
				);
			},
			$groups
		);

		return new WP_REST_Response( array( 'groups' => $data ) );
	}
}
