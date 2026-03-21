<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Oembed.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Oembed;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Oembed.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Oembed
 */
class Test_Event_Oembed extends Base {
	/**
	 * Test instance is retrievable.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_instance(): void {
		$this->assertInstanceOf( Event_Oembed::class, Event_Oembed::get_instance() );
	}

	/**
	 * Test hooks are registered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Event_Oembed::get_instance();

		$this->assertGreaterThan(
			0,
			has_filter( 'oembed_response_data', array( $gatherpress_instance, 'enhance_oembed_response' ) )
		);
	}

	/**
	 * Test enhance_oembed_response does not modify non-event posts.
	 *
	 * @covers ::enhance_oembed_response
	 *
	 * @return void
	 */
	public function test_enhance_oembed_response_non_event(): void {
		$gatherpress_post     = $this->mock->post()->get();
		$gatherpress_instance = Event_Oembed::get_instance();
		$gatherpress_data     = array( 'title' => 'Test Post' );

		$gatherpress_result = $gatherpress_instance->enhance_oembed_response( $gatherpress_data, $gatherpress_post, 600, 400 );

		// Should return unmodified data for non-events.
		$this->assertArrayNotHasKey( 'gatherpress_datetime', $gatherpress_result );
	}
}
