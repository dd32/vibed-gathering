<?php
/**
 * WordPress.org Events API compatible feed endpoint.
 *
 * Exposes a public, paginated, cacheable REST endpoint that returns events
 * across all group sites in the network. Designed to replace Meetup.com as
 * the data source for the official-wordpress-events aggregation plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Events_Feed_Api.
 *
 * Network-wide events feed for the official-wordpress-events aggregation plugin.
 * Returns upcoming events across all group sites with location and group data.
 *
 * @since 1.0.0
 */
class Events_Feed_Api {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Default number of events per page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Maximum number of events per page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Cache max-age in seconds (5 minutes).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CACHE_MAX_AGE = 300;

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
	}

	/**
	 * Register the events feed REST route.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/events-feed',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'description'       => __( 'Current page of the collection.', 'gatherpress' ),
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'description'       => __( 'Maximum number of items to return per page.', 'gatherpress' ),
						'type'              => 'integer',
						'default'           => self::DEFAULT_PER_PAGE,
						'minimum'           => 1,
						'maximum'           => self::MAX_PER_PAGE,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Retrieve events across all group sites in the network.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response The events feed response.
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, intval( $request->get_param( 'page' ) ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, intval( $request->get_param( 'per_page' ) ) ) );

		// Use transient cache for expensive cross-site queries (5 min TTL).
		$gatherpress_cache_key = 'gatherpress_events_feed_' . $page . '_' . $per_page;
		$gatherpress_cached    = get_site_transient( $gatherpress_cache_key );

		if ( is_array( $gatherpress_cached ) ) {
			$events = $gatherpress_cached['events'];
			$total  = $gatherpress_cached['total'];
		} else {
			$total       = 0;
			$site_events = $this->query_network_events( $page, $per_page, $total );

			$events = array();
			foreach ( $site_events as $item ) {
				$events[] = $this->format_event( $item );
			}

			set_site_transient(
				$gatherpress_cache_key,
				array(
					'events' => $events,
					'total'  => $total,
				),
				self::CACHE_MAX_AGE
			);
		}

		$total_pages = (int) ceil( $total / $per_page );

		$response = new WP_REST_Response( $events, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE );

		// ETag for conditional requests.
		$etag = '"' . md5( wp_json_encode( $events ) ) . '"';
		$response->header( 'ETag', $etag );

		// Handle If-None-Match for 304 responses.
		$if_none_match = $request->get_header( 'If-None-Match' );
		if ( $if_none_match && trim( $if_none_match, '" ' ) === trim( $etag, '"' ) ) {
			return new WP_REST_Response( null, 304 );
		}

		return $response;
	}

	/**
	 * Query events across all sites in the network.
	 *
	 * Iterates over group sites, collecting upcoming events,
	 * then sorts by start date and applies pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param int $page     Current page number.
	 * @param int $per_page Events per page.
	 * @param int $total    Total event count (passed by reference).
	 * @return array[] Array of event data arrays.
	 */
	protected function query_network_events( int $page, int $per_page, int &$total ): array {
		// If not multisite, query current site only.
		if ( ! is_multisite() ) {
			return $this->query_single_site_events( $page, $per_page, $total );
		}

		$all_events = array();

		$sites = get_sites(
			array(
				'number'   => 0,
				'public'   => 1,
				'archived' => 0,
				'deleted'  => 0,
				'spam'     => 0,
				'fields'   => 'ids',
			)
		);

		foreach ( $sites as $site_id ) {
			$site_id = (int) $site_id;

			switch_to_blog( $site_id );

			if ( ! post_type_exists( Event::POST_TYPE ) ) {
				restore_current_blog();
				continue;
			}

			$site_events = $this->get_site_upcoming_events( $site_id );
			$all_events  = array_merge( $all_events, $site_events );

			restore_current_blog();
		}

		// Sort by start date ascending.
		usort(
			$all_events,
			static function ( $a, $b ) {
				return strcmp( $a['start_date'], $b['start_date'] );
			}
		);

		$total  = count( $all_events );
		$offset = ( $page - 1 ) * $per_page;

		return array_slice( $all_events, $offset, $per_page );
	}

	/**
	 * Query events from a single site (non-multisite fallback).
	 *
	 * @since 1.0.0
	 *
	 * @param int $page     Current page number.
	 * @param int $per_page Events per page.
	 * @param int $total    Total event count (passed by reference).
	 * @return array[] Array of event data arrays.
	 */
	protected function query_single_site_events( int $page, int $per_page, int &$total ): array {
		$site_id    = get_current_blog_id();
		$all_events = $this->get_site_upcoming_events( $site_id );

		$total  = count( $all_events );
		$offset = ( $page - 1 ) * $per_page;

		return array_slice( $all_events, $offset, $per_page );
	}

	/**
	 * Get upcoming events from a specific site.
	 *
	 * @since 1.0.0
	 *
	 * @param int $site_id The site/blog ID.
	 * @return array[] Array of event data arrays.
	 */
	protected function get_site_upcoming_events( int $site_id ): array {
		$events = array();

		$query = new WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'gatherpress_datetime_end_gmt',
						'value'   => gmdate( Event::DATETIME_FORMAT ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'meta_key'       => 'gatherpress_datetime_start_gmt', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);

		$blog_details = get_blog_details( $site_id );
		$group_name   = $blog_details ? $blog_details->blogname : '';
		$group_url    = $blog_details ? $blog_details->siteurl : '';

		foreach ( $query->posts as $post ) {
			// Skip cancelled events.
			if ( Event_Status::is_cancelled( $post->ID ) ) {
				continue;
			}

			$event     = new Event( $post->ID );
			$venue     = $event->get_venue_information();
			$location  = $this->extract_location( $venue );
			$datetime  = $event->get_datetime();

			$events[] = array(
				'post'       => $post,
				'blog_id'    => $site_id,
				'group_name' => $group_name,
				'group_url'  => $group_url,
				'location'   => $location,
				'start_date' => $datetime['datetime_start_gmt'] ?? '',
				'end_date'   => $datetime['datetime_end_gmt'] ?? '',
				'permalink'  => get_permalink( $post ),
			);
		}

		return $events;
	}

	/**
	 * Extract location data from venue information.
	 *
	 * @since 1.0.0
	 *
	 * @param array $venue_info The venue information array.
	 * @return array{latitude: string, longitude: string, city: string, country: string} Location data.
	 */
	protected function extract_location( array $venue_info ): array {
		return array(
			'latitude'  => (string) ( $venue_info['latitude'] ?? '' ),
			'longitude' => (string) ( $venue_info['longitude'] ?? '' ),
			'city'      => (string) ( $venue_info['city'] ?? '' ),
			'country'   => (string) ( $venue_info['country'] ?? '' ),
		);
	}

	/**
	 * Format an event data array into the structure expected by
	 * the official-wordpress-events plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Event data from query methods.
	 * @return array Formatted event data.
	 */
	protected function format_event( array $item ): array {
		$post = $item['post'];

		return array(
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'description' => wp_strip_all_tags( $post->post_content ),
			'url'         => esc_url( $item['permalink'] ),
			'date'        => $item['start_date'],
			'end_date'    => $item['end_date'],
			'location'    => array(
				'latitude'  => $item['location']['latitude'],
				'longitude' => $item['location']['longitude'],
				'city'      => $item['location']['city'],
				'country'   => $item['location']['country'],
			),
			'group'       => array(
				'name' => $item['group_name'],
				'url'  => esc_url( $item['group_url'] ),
			),
		);
	}
}
