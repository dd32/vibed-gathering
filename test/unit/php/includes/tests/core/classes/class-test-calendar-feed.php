<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar_Feed.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Calendar_Feed;
use GatherPress\Tests\Base;

/**
 * Class Test_Calendar_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar_Feed
 */
class Test_Calendar_Feed extends Base {
	/**
	 * Test that generate_feed produces valid iCal output.
	 *
	 * @covers ::generate_feed
	 *
	 * @return void
	 */
	public function test_generate_feed_structure(): void {
		$gatherpress_instance = Calendar_Feed::get_instance();
		$gatherpress_ics      = $gatherpress_instance->generate_feed();

		// Must start with VCALENDAR.
		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $gatherpress_ics );
		$this->assertStringContainsString( 'END:VCALENDAR', $gatherpress_ics );

		// Must have required iCal properties.
		$this->assertStringContainsString( 'VERSION:2.0', $gatherpress_ics );
		$this->assertStringContainsString( 'PRODID:', $gatherpress_ics );
		$this->assertStringContainsString( 'CALSCALE:GREGORIAN', $gatherpress_ics );
	}

	/**
	 * Test that generate_feed includes site name.
	 *
	 * @covers ::generate_feed
	 *
	 * @return void
	 */
	public function test_generate_feed_includes_site_name(): void {
		$gatherpress_instance = Calendar_Feed::get_instance();
		$gatherpress_ics      = $gatherpress_instance->generate_feed();

		$this->assertStringContainsString( 'X-WR-CALNAME:', $gatherpress_ics );
	}

	/**
	 * Test that hooks are registered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Calendar_Feed::get_instance();

		$this->assertGreaterThan(
			0,
			has_action( 'init', array( $gatherpress_instance, 'register_rewrite_rules' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'wp_head', array( $gatherpress_instance, 'add_feed_discovery_link' ) )
		);
	}

	/**
	 * Test query vars include the calendar feed var.
	 *
	 * @covers ::add_query_vars
	 *
	 * @return void
	 */
	public function test_add_query_vars(): void {
		$gatherpress_instance = Calendar_Feed::get_instance();
		$gatherpress_vars     = $gatherpress_instance->add_query_vars( array() );

		$this->assertContains( 'gatherpress_calendar_feed', $gatherpress_vars );
	}
}
