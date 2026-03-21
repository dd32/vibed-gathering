<?php
/**
 * Group application workflow for GatherPress.
 *
 * Manages the application process for new community groups, including
 * submission, review, approval, and automatic site provisioning.
 * Applications are stored as a custom post type on the main network site.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Group_Application.
 *
 * Handles group application submission, review workflow, and site provisioning.
 *
 * @since 1.0.0
 */
class Group_Application {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Custom post type for group applications.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const POST_TYPE = 'gp_group_application';

	/**
	 * Application status: Pending review.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_PENDING = 'gp-pending';

	/**
	 * Application status: Under review.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_REVIEW = 'gp-review';

	/**
	 * Application status: Approved.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_APPROVED = 'gp-approved';

	/**
	 * Application status: Rejected.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STATUS_REJECTED = 'gp-rejected';

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
		// Only register on the main site.
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register the group application post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Group Applications', 'gatherpress' ),
					'singular_name'      => __( 'Group Application', 'gatherpress' ),
					'add_new_item'       => __( 'New Group Application', 'gatherpress' ),
					'edit_item'          => __( 'Review Application', 'gatherpress' ),
					'view_item'          => __( 'View Application', 'gatherpress' ),
					'search_items'       => __( 'Search Applications', 'gatherpress' ),
					'not_found'          => __( 'No applications found.', 'gatherpress' ),
					'not_found_in_trash' => __( 'No applications found in Trash.', 'gatherpress' ),
					'all_items'          => __( 'Group Applications', 'gatherpress' ),
					'menu_name'          => __( 'Applications', 'gatherpress' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_rest'    => true,
				'rest_base'       => 'gatherpress_applications',
				'show_in_menu'    => 'edit.php?post_type=gatherpress_event',
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					// Applications are created only via the REST API endpoint.
					// Direct post creation is restricted to admins.
					'create_posts' => 'manage_options',
				),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Register custom post statuses for the application workflow.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_statuses(): void {
		register_post_status(
			self::STATUS_PENDING,
			array(
				'label'                     => __( 'Pending Review', 'gatherpress' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of applications. */
				'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'gatherpress' ),
			)
		);

		register_post_status(
			self::STATUS_REVIEW,
			array(
				'label'                     => __( 'Under Review', 'gatherpress' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of applications. */
				'label_count'               => _n_noop( 'Under Review <span class="count">(%s)</span>', 'Under Review <span class="count">(%s)</span>', 'gatherpress' ),
			)
		);

		register_post_status(
			self::STATUS_APPROVED,
			array(
				'label'                     => __( 'Approved', 'gatherpress' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of applications. */
				'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'gatherpress' ),
			)
		);

		register_post_status(
			self::STATUS_REJECTED,
			array(
				'label'                     => __( 'Rejected', 'gatherpress' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of applications. */
				'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'gatherpress' ),
			)
		);
	}

	/**
	 * Register REST API endpoints for group applications.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/group-application/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_application' ),
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'group_name'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'city'           => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'country'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'frequency'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => 'monthly',
					),
					'organizer_info' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/group-application/review',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'review_application' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'action'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notes'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);
	}

	/**
	 * Handle a new group application submission.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function submit_application( WP_REST_Request $request ) {
		$user_id    = get_current_user_id();
		$group_name = $request->get_param( 'group_name' );

		// Prevent duplicate applications — check if user already has a pending or under-review application.
		$gatherpress_existing = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( self::STATUS_PENDING, self::STATUS_REVIEW ),
				'author'         => $user_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $gatherpress_existing ) ) {
			return new WP_Error(
				'duplicate_application',
				__( 'You already have a pending application. Please wait for it to be reviewed before submitting another.', 'gatherpress' ),
				array( 'status' => 409 )
			);
		}

		// Create the application post.
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_title'   => $group_name,
				'post_content' => $request->get_param( 'description' ),
				'post_status'  => self::STATUS_PENDING,
				'post_author'  => $user_id,
			)
		);

		if ( 0 === $post_id ) {
			return new WP_Error(
				'submission_failed',
				__( 'Failed to submit application.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		// Store application metadata.
		update_post_meta( $post_id, 'gatherpress_app_city', $request->get_param( 'city' ) );
		update_post_meta( $post_id, 'gatherpress_app_country', $request->get_param( 'country' ) );
		update_post_meta( $post_id, 'gatherpress_app_frequency', $request->get_param( 'frequency' ) );
		update_post_meta( $post_id, 'gatherpress_app_organizer_info', $request->get_param( 'organizer_info' ) );
		update_post_meta( $post_id, 'gatherpress_app_submitted_at', current_time( 'mysql', true ) );

		// Notify administrators.
		$this->notify_admins_of_application( $post_id, $group_name );

		/**
		 * Fires after a group application is submitted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id The application post ID.
		 * @param int $user_id The applicant user ID.
		 */
		do_action( 'gatherpress_group_application_submitted', $post_id, $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'post_id' => $post_id,
				'message' => __( 'Your group application has been submitted and is pending review.', 'gatherpress' ),
			),
			201
		);
	}

	/**
	 * Handle an application review action (approve/reject/review).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function review_application( WP_REST_Request $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$action  = $request->get_param( 'action' );
		$notes   = $request->get_param( 'notes' );

		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'invalid_application',
				__( 'Application not found.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		$valid_actions = array( 'approve', 'reject', 'start_review' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return new WP_Error(
				'invalid_action',
				__( 'Invalid review action.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		// Store review notes.
		if ( ! empty( $notes ) ) {
			add_post_meta(
				$post_id,
				'gatherpress_app_review_note',
				array(
					'note'      => $notes,
					'reviewer'  => get_current_user_id(),
					'timestamp' => current_time( 'mysql', true ),
				)
			);
		}

		$new_status = '';
		$message    = '';

		switch ( $action ) {
			case 'start_review':
				$new_status = self::STATUS_REVIEW;
				$message    = __( 'Application is now under review.', 'gatherpress' );
				break;

			case 'approve':
				$new_status = self::STATUS_APPROVED;
				$message    = __( 'Application approved.', 'gatherpress' );
				$this->provision_group_site( $post_id );
				break;

			case 'reject':
				$new_status = self::STATUS_REJECTED;
				$message    = __( 'Application rejected.', 'gatherpress' );
				break;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			)
		);

		update_post_meta( $post_id, 'gatherpress_app_' . $action . '_at', current_time( 'mysql', true ) );
		update_post_meta( $post_id, 'gatherpress_app_' . $action . '_by', get_current_user_id() );

		// Notify the applicant.
		$this->notify_applicant( $post_id, $action, $notes );

		/**
		 * Fires after a group application is reviewed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $post_id The application post ID.
		 * @param string $action  The review action taken.
		 */
		do_action( 'gatherpress_group_application_reviewed', $post_id, $action );

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $new_status,
				'message' => $message,
			)
		);
	}

	/**
	 * Provision a new group site when an application is approved.
	 *
	 * Creates a new site in the multisite network and assigns the
	 * applicant as the organizer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $application_id The application post ID.
	 * @return int|false The new blog ID, or false on failure.
	 */
	private function provision_group_site( int $application_id ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$post    = get_post( $application_id );
		$user_id = (int) $post->post_author;
		$title   = $post->post_title;
		$slug    = sanitize_title( $title );

		// Get the network domain and path.
		$network = get_network();
		$domain  = $network->domain;
		$path    = $network->path . $slug . '/';

		// Create the new site.
		$blog_id = wpmu_create_blog( $domain, $path, $title, $user_id );

		if ( is_wp_error( $blog_id ) ) {
			return false;
		}

		// Store the blog ID on the application.
		update_post_meta( $application_id, 'gatherpress_app_blog_id', $blog_id );

		// Set up the group site.
		switch_to_blog( $blog_id );

		// Set the group location from application data.
		$city    = get_post_meta( $application_id, 'gatherpress_app_city', true );
		$country = get_post_meta( $application_id, 'gatherpress_app_country', true );

		update_option(
			Group::OPTION_LOCATION,
			wp_json_encode(
				array(
					'city'    => $city,
					'country' => $country,
				)
			)
		);
		update_option( Group::OPTION_STATUS, 'active' );
		update_option( 'blogdescription', wp_strip_all_tags( $post->post_content ) );

		// Make the applicant an organizer.
		$user = new \WP_User( $user_id );
		$user->set_role( Group::ROLE_ORGANIZER );

		restore_current_blog();

		/**
		 * Fires after a group site is provisioned from an approved application.
		 *
		 * @since 1.0.0
		 *
		 * @param int $blog_id        The new blog ID.
		 * @param int $application_id The application post ID.
		 * @param int $user_id        The organizer user ID.
		 */
		do_action( 'gatherpress_group_site_provisioned', $blog_id, $application_id, $user_id );

		return $blog_id;
	}

	/**
	 * Notify administrators of a new group application.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id    The application post ID.
	 * @param string $group_name The proposed group name.
	 * @return void
	 */
	private function notify_admins_of_application( int $post_id, string $group_name ): void {
		$admin_email = get_option( 'admin_email' );

		/* translators: %s: group name. */
		$subject = sprintf( __( 'New Group Application: %s', 'gatherpress' ), $group_name );

		$content = Email::render_email_body(
			array(
				'greeting'    => __( 'New Group Application', 'gatherpress' ),
				'message'     => sprintf(
					/* translators: %s: group name. */
					__( 'A new group application has been submitted for <strong>%s</strong>. Please review it at your earliest convenience.', 'gatherpress' ),
					esc_html( $group_name )
				),
				'button_text' => __( 'Review Application', 'gatherpress' ),
				'button_url'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			)
		);

		Email::send( $admin_email, $subject, $content );
	}

	/**
	 * Notify the applicant about their application status.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The application post ID.
	 * @param string $action  The review action (approve, reject, start_review).
	 * @param string $notes   Optional reviewer notes.
	 * @return void
	 */
	private function notify_applicant( int $post_id, string $action, string $notes ): void {
		$post = get_post( $post_id );
		$user = get_user_by( 'id', $post->post_author );

		if ( ! $user ) {
			return;
		}

		$group_name = $post->post_title;

		$messages = array(
			'approve'      => sprintf(
				/* translators: %s: group name. */
				__( 'Congratulations! Your group application for <strong>%s</strong> has been approved. Your group site has been created and is ready to use.', 'gatherpress' ),
				esc_html( $group_name )
			),
			'reject'       => sprintf(
				/* translators: %s: group name. */
				__( 'We\'re sorry, but your group application for <strong>%s</strong> was not approved at this time.', 'gatherpress' ),
				esc_html( $group_name )
			),
			'start_review' => sprintf(
				/* translators: %s: group name. */
				__( 'Your group application for <strong>%s</strong> is now being reviewed. We\'ll be in touch soon.', 'gatherpress' ),
				esc_html( $group_name )
			),
		);

		$message = $messages[ $action ] ?? '';

		if ( empty( $message ) ) {
			return;
		}

		if ( ! empty( $notes ) ) {
			$message .= '<br><br>';
			/* translators: %s: reviewer notes. */
			$message .= sprintf( __( '<strong>Notes:</strong> %s', 'gatherpress' ), esc_html( $notes ) );
		}

		// Add link to the new group site if approved.
		$button_text = '';
		$button_url  = '';

		if ( 'approve' === $action ) {
			$blog_id = get_post_meta( $post_id, 'gatherpress_app_blog_id', true );
			if ( $blog_id ) {
				$button_text = __( 'Visit Your Group', 'gatherpress' );
				$button_url  = get_blog_option( (int) $blog_id, 'siteurl', '' );
			}
		}

		$subjects = array(
			/* translators: %s: group name. */
			'approve'      => sprintf( __( 'Approved: %s', 'gatherpress' ), $group_name ),
			/* translators: %s: group name. */
			'reject'       => sprintf( __( 'Application Update: %s', 'gatherpress' ), $group_name ),
			/* translators: %s: group name. */
			'start_review' => sprintf( __( 'Application Under Review: %s', 'gatherpress' ), $group_name ),
		);

		$subject = $subjects[ $action ] ?? '';

		$content = Email::render_email_body(
			array(
				'greeting'    => sprintf(
					/* translators: %s: user display name. */
					__( 'Hi %s,', 'gatherpress' ),
					$user->display_name
				),
				'message'     => $message,
				'button_text' => $button_text,
				'button_url'  => $button_url,
			)
		);

		Email::send( $user->user_email, $subject, $content );
	}
}
