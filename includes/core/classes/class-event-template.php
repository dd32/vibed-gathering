<?php
/**
 * Event template and duplication support for GatherPress.
 *
 * Provides event duplication and template functionality so organizers can
 * quickly create new events based on previous ones.
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
 * Class Event_Template.
 *
 * Handles event duplication and template creation.
 *
 * @since 1.0.0
 */
class Event_Template {
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
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_filter(
			sprintf( 'post_row_actions' ),
			array( $this, 'add_duplicate_action' ),
			10,
			2
		);
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
			sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
			'/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
				),
			)
		);
	}

	/**
	 * Duplicate an event.
	 *
	 * Creates a new draft event with the same content, settings, and metadata
	 * as the source event but without RSVPs or dates.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response.
	 */
	public function duplicate_event( WP_REST_Request $request ) {
		$gatherpress_source_id = intval( $request->get_param( 'post_id' ) );
		$gatherpress_source    = get_post( $gatherpress_source_id );

		if ( ! $gatherpress_source || Event::POST_TYPE !== $gatherpress_source->post_type ) {
			return new WP_Error(
				'invalid_event',
				__( 'Source event not found.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		// Create the duplicate as a draft.
		$gatherpress_new_id = wp_insert_post(
			array(
				'post_type'    => Event::POST_TYPE,
				/* translators: %s: original event title. */
				'post_title'   => sprintf( __( '%s (Copy)', 'gatherpress' ), $gatherpress_source->post_title ),
				'post_content' => $gatherpress_source->post_content,
				'post_excerpt' => $gatherpress_source->post_excerpt,
				'post_status'  => 'draft',
				'post_author'  => (int) get_current_user_id(),
			)
		);

		if ( 0 === $gatherpress_new_id ) {
			return new WP_Error(
				'duplicate_failed',
				__( 'Failed to duplicate event.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		// Copy relevant meta from source.
		$gatherpress_meta_keys = array(
			'gatherpress_max_guest_limit',
			'gatherpress_max_attendance_limit',
			'gatherpress_enable_anonymous_rsvp',
			'gatherpress_enable_initial_decline',
			'gatherpress_online_event_link',
		);

		foreach ( $gatherpress_meta_keys as $gatherpress_key ) {
			$gatherpress_value = get_post_meta( $gatherpress_source_id, $gatherpress_key, true );
			if ( '' !== $gatherpress_value && false !== $gatherpress_value ) {
				update_post_meta( $gatherpress_new_id, $gatherpress_key, $gatherpress_value );
			}
		}

		// Copy venue taxonomy terms.
		$gatherpress_venue_terms = wp_get_object_terms( $gatherpress_source_id, '_gatherpress_venue', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $gatherpress_venue_terms ) && ! empty( $gatherpress_venue_terms ) ) {
			wp_set_object_terms( $gatherpress_new_id, $gatherpress_venue_terms, '_gatherpress_venue' );
		}

		// Copy topic taxonomy terms.
		$gatherpress_topic_terms = wp_get_object_terms( $gatherpress_source_id, 'gatherpress_topic', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $gatherpress_topic_terms ) && ! empty( $gatherpress_topic_terms ) ) {
			wp_set_object_terms( $gatherpress_new_id, $gatherpress_topic_terms, 'gatherpress_topic' );
		}

		// Copy featured image.
		$gatherpress_thumbnail_id = get_post_thumbnail_id( $gatherpress_source_id );
		if ( $gatherpress_thumbnail_id ) {
			set_post_thumbnail( $gatherpress_new_id, $gatherpress_thumbnail_id );
		}

		/**
		 * Fires after an event is duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param int $gatherpress_new_id    The new event post ID.
		 * @param int $gatherpress_source_id The source event post ID.
		 */
		do_action( 'gatherpress_event_duplicated', $gatherpress_new_id, $gatherpress_source_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'post_id' => $gatherpress_new_id,
				'edit_url' => get_edit_post_link( $gatherpress_new_id, 'raw' ),
			),
			201
		);
	}

	/**
	 * Add a "Duplicate" action link to the events admin list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $gatherpress_actions The row actions.
	 * @param \WP_Post $gatherpress_post    The post object.
	 * @return string[] Modified row actions.
	 */
	public function add_duplicate_action( array $gatherpress_actions, \WP_Post $gatherpress_post ): array {
		if ( Event::POST_TYPE !== $gatherpress_post->post_type ) {
			return $gatherpress_actions;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $gatherpress_actions;
		}

		$gatherpress_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'gatherpress_duplicate',
					'post_id' => $gatherpress_post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'gatherpress_duplicate_' . $gatherpress_post->ID
		);

		$gatherpress_actions['duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $gatherpress_url ),
			esc_html__( 'Duplicate', 'gatherpress' )
		);

		return $gatherpress_actions;
	}
}
