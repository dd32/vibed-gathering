<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Template
 */
class Test_Event_Template extends Base {
	/**
	 * Test that the singleton instance is retrievable.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_instance(): void {
		$this->assertInstanceOf( Event_Template::class, Event_Template::get_instance() );
	}

	/**
	 * Test add_duplicate_action does not modify non-event posts.
	 *
	 * @covers ::add_duplicate_action
	 *
	 * @return void
	 */
	public function test_add_duplicate_action_non_event(): void {
		$gatherpress_post     = $this->mock->post()->get();
		$gatherpress_instance = Event_Template::get_instance();
		$gatherpress_actions  = $gatherpress_instance->add_duplicate_action( array( 'edit' => 'Edit' ), $gatherpress_post );

		$this->assertArrayNotHasKey( 'duplicate', $gatherpress_actions );
	}

	/**
	 * Test add_duplicate_action adds action for event posts.
	 *
	 * @covers ::add_duplicate_action
	 *
	 * @return void
	 */
	public function test_add_duplicate_action_event(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Need edit_posts capability.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$gatherpress_instance = Event_Template::get_instance();
		$gatherpress_actions  = $gatherpress_instance->add_duplicate_action( array( 'edit' => 'Edit' ), $gatherpress_post );

		$this->assertArrayHasKey( 'duplicate', $gatherpress_actions );
		$this->assertStringContainsString( 'gatherpress_duplicate', $gatherpress_actions['duplicate'] );
	}

	/**
	 * Test REST endpoint is registered.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_endpoints_registered(): void {
		$gatherpress_instance = Event_Template::get_instance();

		$this->assertGreaterThan(
			0,
			has_action( 'rest_api_init', array( $gatherpress_instance, 'register_endpoints' ) )
		);
	}
}
