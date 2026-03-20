<?php
/**
 * Handles the setup and management of groups in GatherPress.
 *
 * This class is responsible for integrating groups into the WordPress environment, including the creation
 * of the custom post type for groups, managing group metadata, and creating the membership database table.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Group_Setup.
 *
 * Manages group-related functionalities, including registration of group post types and metadata.
 *
 * @since 1.0.0
 */
class Group_Setup {
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
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action(
			sprintf( 'save_post_%s', Group::POST_TYPE ),
			array( $this, 'add_group_term' ),
			10,
			3
		);
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_group_term' ) );
		add_action(
			sprintf( 'manage_%s_posts_custom_column', Group::POST_TYPE ),
			array( $this, 'custom_columns' ),
			10,
			2
		);
		add_filter(
			sprintf( 'manage_%s_posts_columns', Group::POST_TYPE ),
			array( $this, 'set_custom_columns' )
		);
	}

	/**
	 * Registers the custom post type for Groups.
	 *
	 * This method sets up the custom post type 'Group' with all necessary labels and settings,
	 * enabling it to be used within the WordPress REST API, and configuring its appearance and capabilities
	 * within the WordPress admin area.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'groups' );
		register_post_type(
			Group::POST_TYPE,
			array(
				'labels'        => array(
					'name'                     => _x(
						'Groups',
						'Admin menu and post type general name',
						'gatherpress'
					),
					'singular_name'            => _x(
						'Group',
						'Admin menu and post type singular name',
						'gatherpress'
					),
					'add_new'                  => __( 'Add New', 'gatherpress' ),
					'add_new_item'             => __( 'Add New Group', 'gatherpress' ),
					'edit_item'                => __( 'Edit Group', 'gatherpress' ),
					'new_item'                 => __( 'New Group', 'gatherpress' ),
					'view_item'                => __( 'View Group', 'gatherpress' ),
					'view_items'               => __( 'View Groups', 'gatherpress' ),
					'search_items'             => __( 'Search Groups', 'gatherpress' ),
					'not_found'                => __( 'No Groups found.', 'gatherpress' ),
					'not_found_in_trash'       => __( 'No Groups found in Trash.', 'gatherpress' ),
					'parent_item_colon'        => __( 'Parent Groups:', 'gatherpress' ),
					'all_items'                => __( 'View Groups', 'gatherpress' ),
					'archives'                 => __( 'Group Archives', 'gatherpress' ),
					'attributes'               => __( 'Group Attributes', 'gatherpress' ),
					'insert_into_item'         => __( 'Insert into Group', 'gatherpress' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this Group', 'gatherpress' ),
					'menu_name'                => _x( 'Groups', 'Admin menu label', 'gatherpress' ),
					'filter_items_list'        => __( 'Filter Group list', 'gatherpress' ),
					'filter_by_date'           => __( 'Filter by date', 'gatherpress' ),
					'items_list_navigation'    => __( 'Groups list navigation', 'gatherpress' ),
					'items_list'               => __( 'Groups list', 'gatherpress' ),
					'item_published'           => __( 'Group published.', 'gatherpress' ),
					'item_published_privately' => __( 'Group published privately.', 'gatherpress' ),
					'item_reverted_to_draft'   => __( 'Group reverted to draft.', 'gatherpress' ),
					'item_trashed'             => __( 'Group trashed.', 'gatherpress' ),
					'item_scheduled'           => __( 'Group scheduled.', 'gatherpress' ),
					'item_updated'             => __( 'Group updated.', 'gatherpress' ),
					'item_link'                => _x( 'Group Link', 'Block editor link label', 'gatherpress' ),
					'item_link_description'    => _x(
						'A link to a group.',
						'Block editor link description',
						'gatherpress'
					),
				),
				'show_in_rest'  => true,
				'rest_base'     => 'gatherpress_groups',
				'public'        => true,
				'hierarchical'  => false,
				'template'      => array(
					array(
						'core/paragraph',
						array(
							'placeholder' => __(
								'Describe your group and what kind of events you organize. Let potential members know what to expect.',
								'gatherpress'
							),
						),
					),
				),
				'menu_position' => 5,
				'supports'      => array(
					'title',
					'author',
					'editor',
					'excerpt',
					'thumbnail',
					'revisions',
					'custom-fields',
				),
				'menu_icon'     => 'dashicons-groups',
				'has_archive'   => true,
				'rewrite'       => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get_value( 'general', 'urls', 'groups' ) instead.
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

	/**
	 * Authorization callback for post meta that requires edit_posts capability.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can edit posts, false otherwise.
	 */
	public function can_edit_posts_meta(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers custom meta fields for the Group post type.
	 *
	 * Sets up meta fields associated with the Group post type, such as location,
	 * group type, and status information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$post_meta = array(
			// Group location stored as JSON (city, country, latitude, longitude).
			'gatherpress_group_location' => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
			// Group type: in-person, online, or hybrid.
			'gatherpress_group_type'     => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'in-person',
			),
			// Group status: active, inactive, pending.
			'gatherpress_group_status'   => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => Group::STATUS_ACTIVE,
			),
			// External links stored as JSON.
			'gatherpress_group_links'    => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
		);

		foreach ( $post_meta as $meta_key => $args ) {
			register_post_meta(
				Group::POST_TYPE,
				$meta_key,
				$args
			);
		}
	}

	/**
	 * Registers a custom taxonomy for associating events with groups.
	 *
	 * This taxonomy is programmatically managed and linked to the Event post type.
	 * It allows events to be associated with groups for querying and filtering.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			Group::TAXONOMY,
			Event::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => _x( 'Groups', 'Admin menu and taxonomy general name', 'gatherpress' ),
					'singular_name' => _x( 'Group', 'Admin menu and taxonomy singular name', 'gatherpress' ),
				),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => true,
				'query_var'          => true,
				'publicly_queryable' => true,
				'rewrite'            => false,
				'show_in_rest'       => true,
			)
		);
		// Make this taxonomy visible on event posts within REST responses.
		register_taxonomy_for_object_type( Group::TAXONOMY, Event::POST_TYPE );
	}

	/**
	 * Add a group term when a group post type is first saved.
	 *
	 * This method is responsible for automatically adding a term to the group taxonomy
	 * when a new group post is created and published.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID of the group post.
	 * @param WP_Post $post    The group post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function add_group_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
		}

		if (
			! $update &&
			! empty( $post->post_name ) &&
			'publish' === $post->post_status
		) {
			$group     = new Group( $post_id );
			$term_slug = $group->get_group_term_slug( $post->post_name );
			$title     = html_entity_decode( get_the_title( $post_id ) );
			$term      = term_exists( $term_slug, Group::TAXONOMY );

			if ( empty( $term ) ) {
				wp_insert_term(
					$title,
					Group::TAXONOMY,
					array(
						'slug' => $term_slug,
					)
				);
			}
		}
	}

	/**
	 * Update the group taxonomy term slug when the group post slug changes.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 * @return void
	 */
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		if ( Group::POST_TYPE !== $post_after->post_type ) {
			return;
		}

		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		$group         = new Group( $post_id );
		$old_term_slug = $group->get_group_term_slug( $post_before->post_name );
		$new_term_slug = $group->get_group_term_slug( $post_after->post_name );
		$term          = get_term_by( 'slug', $old_term_slug, Group::TAXONOMY );

		if ( is_a( $term, '\WP_Term' ) ) {
			wp_update_term(
				$term->term_id,
				Group::TAXONOMY,
				array(
					'name' => html_entity_decode( get_the_title( $post_id ) ),
					'slug' => $new_term_slug,
				)
			);
		}
	}

	/**
	 * Delete the group taxonomy term when the group post is deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function delete_group_term( int $post_id ): void {
		if ( Group::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$group     = new Group( $post_id );
		$term_slug = $group->get_group_term_slug();
		$term      = get_term_by( 'slug', $term_slug, Group::TAXONOMY );

		if ( is_a( $term, '\WP_Term' ) ) {
			wp_delete_term( $term->term_id, Group::TAXONOMY );
		}
	}

	/**
	 * Set custom columns for the Groups admin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function set_custom_columns( array $columns ): array {
		$placement = array_search( 'date', array_keys( $columns ), true );

		if ( false === $placement ) {
			$placement = count( $columns );
		}

		$splice = array(
			'group_type'     => __( 'Type', 'gatherpress' ),
			'group_location' => __( 'Location', 'gatherpress' ),
			'member_count'   => __( 'Members', 'gatherpress' ),
		);

		// Insert custom columns before the date column.
		return array_slice( $columns, 0, $placement, true )
			+ $splice
			+ array_slice( $columns, $placement, null, true );
	}

	/**
	 * Render custom column content for the Groups admin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The column name.
	 * @param int    $post_id The post ID.
	 * @return void
	 */
	public function custom_columns( string $column, int $post_id ): void {
		$group = new Group( $post_id );

		switch ( $column ) {
			case 'group_type':
				$type  = $group->get_type();
				$types = array(
					'in-person' => __( 'In-Person', 'gatherpress' ),
					'online'    => __( 'Online', 'gatherpress' ),
					'hybrid'    => __( 'Hybrid', 'gatherpress' ),
				);
				echo esc_html( $types[ $type ] ?? $type );
				break;

			case 'group_location':
				$location = $group->get_location();
				$parts    = array_filter(
					array( $location['city'], $location['country'] )
				);
				echo esc_html( implode( ', ', $parts ) );
				break;

			case 'member_count':
				echo esc_html( (string) $group->get_member_count() );
				break;
		}
	}

	/**
	 * Create the group membership database table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_membership_table(): void {
		global $wpdb;

		$table           = sprintf( Group::MEMBERSHIP_TABLE, $wpdb->prefix );
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			group_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'member',
			status varchar(20) NOT NULL DEFAULT 'active',
			joined_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY group_user (group_id, user_id),
			KEY group_id (group_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
