<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Checkin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Checkin;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Checkin.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Checkin
 */
class Test_Event_Checkin extends Base {
	/**
	 * Test get_checkin_count returns 0 for events with no check-ins.
	 *
	 * @covers ::get_checkin_count
	 *
	 * @return void
	 */
	public function test_get_checkin_count_zero(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( 0, Event_Checkin::get_checkin_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test get_checkin_count counts checked-in RSVPs.
	 *
	 * @covers ::get_checkin_count
	 *
	 * @return void
	 */
	public function test_get_checkin_count_with_checkins(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Create RSVP comments with check-in meta.
		$gatherpress_comment_1 = wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 1,
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $gatherpress_comment_1, Event_Checkin::META_CHECKED_IN, 1 );

		$gatherpress_comment_2 = wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 2,
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $gatherpress_comment_2, Event_Checkin::META_CHECKED_IN, 1 );

		// One without check-in.
		wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 3,
				'comment_approved' => 1,
			)
		);

		$this->assertSame( 2, Event_Checkin::get_checkin_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test META constants are defined.
	 *
	 * @return void
	 */
	public function test_meta_constants(): void {
		$this->assertSame( 'gatherpress_checked_in', Event_Checkin::META_CHECKED_IN );
		$this->assertSame( 'gatherpress_checkin_time', Event_Checkin::META_CHECKIN_TIME );
		$this->assertSame( 'gatherpress_no_show', Event_Checkin::META_NO_SHOW );
	}
}
