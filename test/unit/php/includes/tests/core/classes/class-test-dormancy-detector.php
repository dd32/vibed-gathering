<?php
/**
 * Class handles unit tests for GatherPress\Core\Dormancy_Detector.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Dormancy_Detector;
use GatherPress\Tests\Base;

/**
 * Class Test_Dormancy_Detector.
 *
 * @coversDefaultClass \GatherPress\Core\Dormancy_Detector
 */
class Test_Dormancy_Detector extends Base {
	/**
	 * Test threshold constants.
	 *
	 * @return void
	 */
	public function test_threshold_constants(): void {
		$this->assertSame( 60, Dormancy_Detector::AT_RISK_DAYS );
		$this->assertSame( 90, Dormancy_Detector::DORMANT_DAYS );
	}

	/**
	 * Test cron hook constant.
	 *
	 * @return void
	 */
	public function test_cron_hook_constant(): void {
		$this->assertSame( 'gatherpress_check_dormancy', Dormancy_Detector::CRON_HOOK );
	}

	/**
	 * Test option key constant.
	 *
	 * @return void
	 */
	public function test_option_key_constant(): void {
		$this->assertSame( 'gatherpress_last_event_date', Dormancy_Detector::OPTION_LAST_EVENT );
	}

	/**
	 * Test instance is retrievable.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_instance(): void {
		$this->assertInstanceOf( Dormancy_Detector::class, Dormancy_Detector::get_instance() );
	}

	/**
	 * Test hooks are registered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Dormancy_Detector::get_instance();

		$this->assertGreaterThan(
			0,
			has_action( Dormancy_Detector::CRON_HOOK, array( $gatherpress_instance, 'check_all_groups' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'transition_post_status', array( $gatherpress_instance, 'track_event_activity' ) )
		);
	}
}
