<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Feedback.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Feedback;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Feedback.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Feedback
 */
class Test_Event_Feedback extends Base {
	/**
	 * Test get_average_rating returns 0 for events with no feedback.
	 *
	 * @covers ::get_average_rating
	 *
	 * @return void
	 */
	public function test_get_average_rating_no_feedback(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( 0.0, Event_Feedback::get_average_rating( $gatherpress_post->ID ) );
	}

	/**
	 * Test get_average_rating calculates correctly with feedback.
	 *
	 * @covers ::get_average_rating
	 *
	 * @return void
	 */
	public function test_get_average_rating_with_feedback(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Add feedback comments.
		$gatherpress_comment_1 = wp_insert_comment(
			array(
				'comment_post_ID' => $gatherpress_post->ID,
				'comment_type'    => Event_Feedback::COMMENT_TYPE,
				'comment_content' => 'Great event!',
				'user_id'         => 1,
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $gatherpress_comment_1, Event_Feedback::META_RATING, 5 );

		$gatherpress_comment_2 = wp_insert_comment(
			array(
				'comment_post_ID' => $gatherpress_post->ID,
				'comment_type'    => Event_Feedback::COMMENT_TYPE,
				'comment_content' => 'Good event.',
				'user_id'         => 2,
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $gatherpress_comment_2, Event_Feedback::META_RATING, 3 );

		// Average of 5 and 3 = 4.0.
		$this->assertSame( 4.0, Event_Feedback::get_average_rating( $gatherpress_post->ID ) );
	}

	/**
	 * Test COMMENT_TYPE constant value.
	 *
	 * @return void
	 */
	public function test_comment_type_constant(): void {
		$this->assertSame( 'gatherpress_feedback', Event_Feedback::COMMENT_TYPE );
	}

	/**
	 * Test META_RATING constant value.
	 *
	 * @return void
	 */
	public function test_meta_rating_constant(): void {
		$this->assertSame( 'gatherpress_feedback_rating', Event_Feedback::META_RATING );
	}
}
