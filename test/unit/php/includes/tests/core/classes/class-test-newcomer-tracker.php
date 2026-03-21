<?php
/**
 * Class handles unit tests for GatherPress\Core\Newcomer_Tracker.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Newcomer_Tracker;
use GatherPress\Tests\Base;

/**
 * Class Test_Newcomer_Tracker.
 *
 * @coversDefaultClass \GatherPress\Core\Newcomer_Tracker
 */
class Test_Newcomer_Tracker extends Base {
	/**
	 * Test get_newcomer_count returns 0 with no newcomers.
	 *
	 * @covers ::get_newcomer_count
	 *
	 * @return void
	 */
	public function test_get_newcomer_count_zero(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( 0, Newcomer_Tracker::get_newcomer_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test get_newcomer_count counts flagged RSVPs.
	 *
	 * @covers ::get_newcomer_count
	 *
	 * @return void
	 */
	public function test_get_newcomer_count_with_newcomers(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Create RSVP comment with newcomer flag.
		$gatherpress_comment = wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 1,
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $gatherpress_comment, Newcomer_Tracker::RSVP_META_NEWCOMER, 1 );

		// Create another without the newcomer flag.
		wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 2,
				'comment_approved' => 1,
			)
		);

		$this->assertSame( 1, Newcomer_Tracker::get_newcomer_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test META constants.
	 *
	 * @return void
	 */
	public function test_meta_constants(): void {
		$this->assertSame( 'gatherpress_first_event_id', Newcomer_Tracker::META_FIRST_EVENT );
		$this->assertSame( 'gatherpress_is_newcomer', Newcomer_Tracker::RSVP_META_NEWCOMER );
	}
}
