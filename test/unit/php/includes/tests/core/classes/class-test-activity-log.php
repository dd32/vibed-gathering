<?php
/**
 * Class handles unit tests for GatherPress\Core\Activity_Log.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Activity_Log;
use GatherPress\Tests\Base;

/**
 * Class Test_Activity_Log.
 *
 * @coversDefaultClass \GatherPress\Core\Activity_Log
 */
class Test_Activity_Log extends Base {
	/**
	 * Test TABLE_FORMAT constant.
	 *
	 * @return void
	 */
	public function test_table_format_constant(): void {
		$this->assertSame( '%sgatherpress_activity_log', Activity_Log::TABLE_FORMAT );
	}

	/**
	 * Test instance is retrievable.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_instance(): void {
		$this->assertInstanceOf( Activity_Log::class, Activity_Log::get_instance() );
	}

	/**
	 * Test hooks are registered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Activity_Log::get_instance();

		$this->assertGreaterThan(
			0,
			has_action( 'rest_api_init', array( $gatherpress_instance, 'register_endpoints' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'gatherpress_group_member_added', array( $gatherpress_instance, 'log_member_joined' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'gatherpress_event_cancelled', array( $gatherpress_instance, 'log_event_cancelled' ) )
		);
	}
}
