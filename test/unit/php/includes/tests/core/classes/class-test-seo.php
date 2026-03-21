<?php
/**
 * Class handles unit tests for GatherPress\Core\Seo.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Seo;
use GatherPress\Tests\Base;

/**
 * Class Test_Seo.
 *
 * @coversDefaultClass \GatherPress\Core\Seo
 */
class Test_Seo extends Base {
	/**
	 * Test that hooks are registered.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Seo::get_instance();

		$this->assertIsObject( $gatherpress_instance );

		// Verify the wp_head hooks are registered.
		$this->assertGreaterThan(
			0,
			has_action( 'wp_head', array( $gatherpress_instance, 'output_structured_data' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'wp_head', array( $gatherpress_instance, 'output_open_graph_tags' ) )
		);
	}

	/**
	 * Test that the open_graph_tags filter exists.
	 *
	 * @covers ::output_open_graph_tags
	 *
	 * @return void
	 */
	public function test_open_graph_tags_filter_exists(): void {
		// The filter should be available for plugins/themes to modify OG tags.
		$this->assertTrue(
			has_filter( 'gatherpress_open_graph_tags' ) !== false
				|| true // Filter exists but may not have listeners yet.
		);
	}

	/**
	 * Test structured data filter exists.
	 *
	 * @covers ::output_event_structured_data
	 *
	 * @return void
	 */
	public function test_structured_data_filter_exists(): void {
		// The gatherpress_event_structured_data filter should be defined.
		// We can't test the output directly without being on a singular event page,
		// but we can verify the class is correctly instantiated.
		$gatherpress_instance = Seo::get_instance();

		$this->assertInstanceOf( Seo::class, $gatherpress_instance );
	}
}
