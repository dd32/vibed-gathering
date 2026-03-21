<?php
/**
 * Handles the setup and management of groups in GatherPress.
 *
 * In the multisite architecture, each group is a WordPress site. This class
 * handles registering custom group roles (organizer, co_organizer, member)
 * and managing group site metadata.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Group_Setup.
 *
 * Manages group-related functionalities in a WordPress multisite environment,
 * including custom role registration and group site initialization.
 *
 * @since 1.0.0
 */
class Group_Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Custom roles and their capabilities.
	 *
	 * @since 1.0.0
	 * @var array<string, array{display_name: string, capabilities: array<string, bool>}>
	 */
	const ROLES = array(
		'organizer'    => array(
			'display_name' => 'Organizer',
			'capabilities' => array(
				// Editor-level capabilities.
				'moderate_comments'      => true,
				'manage_categories'      => true,
				'manage_links'           => true,
				'upload_files'           => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'publish_posts'          => true,
				'edit_pages'             => true,
				'read'                   => true,
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_pages'           => true,
				'delete_others_pages'    => true,
				'delete_published_pages' => true,
				'delete_posts'           => true,
				'delete_others_posts'    => true,
				'delete_published_posts' => true,
				'delete_private_posts'   => true,
				'edit_private_posts'     => true,
				'read_private_posts'     => true,
				'delete_private_pages'   => true,
				'edit_private_pages'     => true,
				'read_private_pages'     => true,
				// Organizer-specific capability.
				'manage_options'         => true,
			),
		),
		'co_organizer' => array(
			'display_name' => 'Co-Organizer',
			'capabilities' => array(
				// Editor-level capabilities without manage_options.
				'moderate_comments'      => true,
				'manage_categories'      => true,
				'manage_links'           => true,
				'upload_files'           => true,
				'edit_posts'             => true,
				'edit_others_posts'      => true,
				'edit_published_posts'   => true,
				'publish_posts'          => true,
				'edit_pages'             => true,
				'read'                   => true,
				'edit_others_pages'      => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_pages'           => true,
				'delete_others_pages'    => true,
				'delete_published_pages' => true,
				'delete_posts'           => true,
				'delete_others_posts'    => true,
				'delete_published_posts' => true,
				'delete_private_posts'   => true,
				'edit_private_posts'     => true,
				'read_private_posts'     => true,
				'delete_private_pages'   => true,
				'edit_private_pages'     => true,
				'read_private_pages'     => true,
			),
		),
		'member'       => array(
			'display_name' => 'Member',
			'capabilities' => array(
				// Subscriber-level capabilities.
				'read' => true,
			),
		),
	);

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
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
		add_action( 'init', array( $this, 'register_roles' ) );
		add_action( 'wp_initialize_site', array( $this, 'on_group_site_create' ), 20 );
	}

	/**
	 * Register custom group roles on the current site.
	 *
	 * Uses wp_roles()->is_role() as a guard so add_role() (which writes
	 * to wp_options) is only called once per site, not on every page load.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_roles(): void {
		// Only register roles on non-main sites (group sites).
		if ( is_main_site() ) {
			return;
		}

		$wp_roles = wp_roles();

		foreach ( self::ROLES as $role_slug => $role_data ) {
			if ( ! $wp_roles->is_role( $role_slug ) ) {
				add_role( $role_slug, $role_data['display_name'], $role_data['capabilities'] );
			}
		}
	}

	/**
	 * Initialize a new group site with default GatherPress configuration.
	 *
	 * This runs when a new site is created in the network, setting up
	 * the default group options and registering roles.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Site $new_site The new site object.
	 * @return void
	 */
	public function on_group_site_create( \WP_Site $new_site ): void {
		$blog_id = (int) $new_site->blog_id;

		// Skip the main site.
		if ( get_main_site_id() === $blog_id ) {
			return;
		}

		switch_to_blog( $blog_id );

		// Register group roles on the new site.
		$wp_roles = wp_roles();
		foreach ( self::ROLES as $role_slug => $role_data ) {
			if ( ! $wp_roles->is_role( $role_slug ) ) {
				add_role( $role_slug, $role_data['display_name'], $role_data['capabilities'] );
			}
		}

		// Set default group options.
		update_option( Group::OPTION_STATUS, 'active' );
		update_option( Group::OPTION_TYPE, 'in-person' );
		update_option( Group::OPTION_LOCATION, '' );
		update_option( Group::OPTION_LINKS, '' );

		// Create default event categories (topics).
		$gatherpress_categories = array(
			__( 'In-Person', 'gatherpress' ),
			__( 'Online', 'gatherpress' ),
			__( 'Hybrid', 'gatherpress' ),
			__( 'Workshop', 'gatherpress' ),
			__( 'Presentation', 'gatherpress' ),
			__( 'Social', 'gatherpress' ),
		);

		foreach ( $gatherpress_categories as $gatherpress_cat ) {
			if ( ! term_exists( $gatherpress_cat, 'gatherpress_topic' ) ) {
				wp_insert_term( $gatherpress_cat, 'gatherpress_topic' );
			}
		}

		restore_current_blog();
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Used for URL settings in the admin. Even in multisite mode, we may want
	 * a configurable slug for the group directory page on the main site.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Group', 'Admin menu and post type singular name', 'gatherpress' );
		$slug            = sanitize_title( $slug );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
