<?php
/**
 * Class handles unit tests for GatherPress\Core\Location_Search.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Location_Search;
use GatherPress\Tests\Base;

/**
 * Class Test_Location_Search.
 *
 * @coversDefaultClass \GatherPress\Core\Location_Search
 */
class Test_Location_Search extends Base {
	/**
	 * Test haversine distance between two known points.
	 *
	 * @covers ::haversine_distance
	 *
	 * @return void
	 */
	public function test_haversine_distance_known_points(): void {
		// Melbourne, AU to Sydney, AU is approximately 714 km.
		$gatherpress_distance = Location_Search::haversine_distance(
			-37.8136,
			144.9631,
			-33.8688,
			151.2093
		);

		// Allow 5% margin for Haversine approximation.
		$this->assertGreaterThan( 700, $gatherpress_distance );
		$this->assertLessThan( 730, $gatherpress_distance );
	}

	/**
	 * Test haversine distance for the same point returns 0.
	 *
	 * @covers ::haversine_distance
	 *
	 * @return void
	 */
	public function test_haversine_distance_same_point(): void {
		$gatherpress_distance = Location_Search::haversine_distance(
			51.5074,
			-0.1278,
			51.5074,
			-0.1278
		);

		$this->assertSame( 0.0, $gatherpress_distance );
	}

	/**
	 * Test haversine distance across the equator.
	 *
	 * @covers ::haversine_distance
	 *
	 * @return void
	 */
	public function test_haversine_distance_cross_equator(): void {
		// Singapore (1.35, 103.82) to Jakarta (-6.21, 106.85).
		$gatherpress_distance = Location_Search::haversine_distance(
			1.3521,
			103.8198,
			-6.2088,
			106.8456
		);

		// Approximately 880-900 km.
		$this->assertGreaterThan( 870, $gatherpress_distance );
		$this->assertLessThan( 910, $gatherpress_distance );
	}

	/**
	 * Test haversine distance for antipodal points.
	 *
	 * @covers ::haversine_distance
	 *
	 * @return void
	 */
	public function test_haversine_distance_antipodal(): void {
		// North pole to south pole is approximately 20,015 km.
		$gatherpress_distance = Location_Search::haversine_distance(
			90.0,
			0.0,
			-90.0,
			0.0
		);

		$this->assertGreaterThan( 20000, $gatherpress_distance );
		$this->assertLessThan( 20100, $gatherpress_distance );
	}
}
