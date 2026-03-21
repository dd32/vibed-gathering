<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Status.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Status.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Status
 */
class Test_Event_Status extends Base {
	/**
	 * Test get_status returns 'scheduled' by default.
	 *
	 * @covers ::get_status
	 *
	 * @return void
	 */
	public function test_get_status_default(): void {
		$post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( Event_Status::STATUS_SCHEDULED, Event_Status::get_status( $post->ID ) );
	}

	/**
	 * Test is_cancelled returns false by default.
	 *
	 * @covers ::is_cancelled
	 *
	 * @return void
	 */
	public function test_is_cancelled_default(): void {
		$post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertFalse( Event_Status::is_cancelled( $post->ID ) );
	}

	/**
	 * Test that updating status meta reflects in get_status.
	 *
	 * @covers ::get_status
	 * @covers ::is_cancelled
	 *
	 * @return void
	 */
	public function test_status_updates(): void {
		$post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		update_post_meta( $post->ID, Event_Status::META_STATUS, Event_Status::STATUS_CANCELLED );

		$this->assertSame( Event_Status::STATUS_CANCELLED, Event_Status::get_status( $post->ID ) );
		$this->assertTrue( Event_Status::is_cancelled( $post->ID ) );
	}

	/**
	 * Test postponed status.
	 *
	 * @covers ::get_status
	 *
	 * @return void
	 */
	public function test_postponed_status(): void {
		$post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		update_post_meta( $post->ID, Event_Status::META_STATUS, Event_Status::STATUS_POSTPONED );

		$this->assertSame( Event_Status::STATUS_POSTPONED, Event_Status::get_status( $post->ID ) );
		$this->assertFalse( Event_Status::is_cancelled( $post->ID ) );
	}

	/**
	 * Test get_statuses returns all valid statuses.
	 *
	 * @covers ::get_statuses
	 *
	 * @return void
	 */
	public function test_get_statuses(): void {
		$statuses = Event_Status::get_statuses();

		$this->assertArrayHasKey( Event_Status::STATUS_SCHEDULED, $statuses );
		$this->assertArrayHasKey( Event_Status::STATUS_CANCELLED, $statuses );
		$this->assertArrayHasKey( Event_Status::STATUS_POSTPONED, $statuses );
		$this->assertCount( 3, $statuses );
	}

	/**
	 * Test that add_status_to_title modifies cancelled event titles.
	 *
	 * @covers ::add_status_to_title
	 *
	 * @return void
	 */
	public function test_add_status_to_title_cancelled(): void {
		$post = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'My Event',
			)
		)->get();

		update_post_meta( $post->ID, Event_Status::META_STATUS, Event_Status::STATUS_CANCELLED );

		$instance = Event_Status::get_instance();

		// Simulate front-end context by ensuring we're not in admin.
		$this->assertStringContainsString(
			'CANCELLED',
			$instance->add_status_to_title( 'My Event', $post->ID )
		);
	}

	/**
	 * Test that non-event posts are not modified.
	 *
	 * @covers ::add_status_to_title
	 *
	 * @return void
	 */
	public function test_add_status_to_title_non_event(): void {
		$post     = $this->mock->post()->get();
		$instance = Event_Status::get_instance();

		$this->assertSame(
			'Test Post',
			$instance->add_status_to_title( 'Test Post', $post->ID )
		);
	}
}
