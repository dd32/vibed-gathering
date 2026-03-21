<?php
/**
 * Location-based search for GatherPress.
 *
 * Provides Haversine formula-based geographic search to find groups
 * and events near a given latitude/longitude coordinate pair.
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
 * Class Location_Search.
 *
 * Provides geographic proximity search for groups using the Haversine formula.
 *
 * @since 1.0.0
 */
class Location_Search {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Earth's radius in kilometers.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	const EARTH_RADIUS_KM = 6371.0;

	/**
	 * Default search radius in kilometers.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_RADIUS_KM = 100;

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
		// Location search across groups requires multisite.
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register REST API endpoints for location search.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/groups/nearby',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_nearby_groups' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lat'    => array(
						'required'          => true,
						'validate_callback' => static function ( $gatherpress_val ) {
							return is_numeric( $gatherpress_val ) && abs( (float) $gatherpress_val ) <= 90;
						},
					),
					'lng'    => array(
						'required'          => true,
						'validate_callback' => static function ( $gatherpress_val ) {
							return is_numeric( $gatherpress_val ) && abs( (float) $gatherpress_val ) <= 180;
						},
					),
					'radius' => array(
						'required'          => false,
						'default'           => self::DEFAULT_RADIUS_KM,
						'sanitize_callback' => 'absint',
					),
					'limit'  => array(
						'required'          => false,
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Search for groups near a geographic coordinate.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The response with nearby groups.
	 */
	public function search_nearby_groups( WP_REST_Request $request ): WP_REST_Response {
		$gatherpress_lat    = (float) $request->get_param( 'lat' );
		$gatherpress_lng    = (float) $request->get_param( 'lng' );
		$gatherpress_radius = (int) $request->get_param( 'radius' );
		$gatherpress_limit  = min( (int) $request->get_param( 'limit' ), 100 );

		$gatherpress_groups = Group::get_all_groups( array( 'number' => 500 ) );
		$gatherpress_results = array();

		foreach ( $gatherpress_groups as $gatherpress_group ) {
			$gatherpress_location = $gatherpress_group->get_location();

			if ( empty( $gatherpress_location['latitude'] ) || empty( $gatherpress_location['longitude'] ) ) {
				continue;
			}

			$gatherpress_distance = self::haversine_distance(
				$gatherpress_lat,
				$gatherpress_lng,
				(float) $gatherpress_location['latitude'],
				(float) $gatherpress_location['longitude']
			);

			if ( $gatherpress_distance <= $gatherpress_radius ) {
				$gatherpress_results[] = array(
					'blog_id'      => $gatherpress_group->get_blog_id(),
					'name'         => $gatherpress_group->get_name(),
					'url'          => $gatherpress_group->get_url(),
					'description'  => $gatherpress_group->get_description(),
					'location'     => $gatherpress_location,
					'distance_km'  => round( $gatherpress_distance, 1 ),
					'member_count' => $gatherpress_group->get_member_count(),
					'type'         => $gatherpress_group->get_type(),
				);
			}
		}

		// Sort by distance.
		usort(
			$gatherpress_results,
			static function ( $gatherpress_a, $gatherpress_b ) {
				return $gatherpress_a['distance_km'] <=> $gatherpress_b['distance_km'];
			}
		);

		// Limit results.
		$gatherpress_results = array_slice( $gatherpress_results, 0, $gatherpress_limit );

		return new WP_REST_Response(
			array(
				'groups' => $gatherpress_results,
				'total'  => count( $gatherpress_results ),
			)
		);
	}

	/**
	 * Calculate the distance between two geographic coordinates using the Haversine formula.
	 *
	 * @since 1.0.0
	 *
	 * @param float $gatherpress_lat1 Latitude of point 1.
	 * @param float $gatherpress_lng1 Longitude of point 1.
	 * @param float $gatherpress_lat2 Latitude of point 2.
	 * @param float $gatherpress_lng2 Longitude of point 2.
	 * @return float Distance in kilometers.
	 */
	public static function haversine_distance(
		float $gatherpress_lat1,
		float $gatherpress_lng1,
		float $gatherpress_lat2,
		float $gatherpress_lng2
	): float {
		$gatherpress_lat1_rad = deg2rad( $gatherpress_lat1 );
		$gatherpress_lat2_rad = deg2rad( $gatherpress_lat2 );
		$gatherpress_dlat     = deg2rad( $gatherpress_lat2 - $gatherpress_lat1 );
		$gatherpress_dlng     = deg2rad( $gatherpress_lng2 - $gatherpress_lng1 );

		$gatherpress_a = sin( $gatherpress_dlat / 2 ) * sin( $gatherpress_dlat / 2 )
			+ cos( $gatherpress_lat1_rad ) * cos( $gatherpress_lat2_rad )
			* sin( $gatherpress_dlng / 2 ) * sin( $gatherpress_dlng / 2 );

		$gatherpress_c = 2 * atan2( sqrt( $gatherpress_a ), sqrt( 1 - $gatherpress_a ) );

		return self::EARTH_RADIUS_KM * $gatherpress_c;
	}
}
