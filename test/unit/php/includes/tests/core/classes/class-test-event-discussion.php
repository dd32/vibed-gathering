<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Discussion.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Discussion;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Discussion.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Discussion
 */
class Test_Event_Discussion extends Base {
	/**
	 * Test get_discussion_count returns 0 for events with no comments.
	 *
	 * @covers ::get_discussion_count
	 *
	 * @return void
	 */
	public function test_get_discussion_count_zero(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( 0, Event_Discussion::get_discussion_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test get_discussion_count excludes RSVP comments.
	 *
	 * @covers ::get_discussion_count
	 *
	 * @return void
	 */
	public function test_get_discussion_count_excludes_rsvps(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Add a regular discussion comment.
		wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'comment',
				'comment_content'  => 'Great event!',
				'comment_approved' => 1,
			)
		);

		// Add an RSVP comment (should be excluded).
		wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_rsvp',
				'user_id'          => 1,
				'comment_approved' => 1,
			)
		);

		// Add a feedback comment (should also be excluded).
		wp_insert_comment(
			array(
				'comment_post_ID'  => $gatherpress_post->ID,
				'comment_type'     => 'gatherpress_feedback',
				'comment_content'  => 'Rating',
				'comment_approved' => 1,
			)
		);

		// Only the regular comment should be counted.
		$this->assertSame( 1, Event_Discussion::get_discussion_count( $gatherpress_post->ID ) );
	}

	/**
	 * Test get_gallery returns empty array for events without gallery.
	 *
	 * @covers ::get_gallery
	 *
	 * @return void
	 */
	public function test_get_gallery_empty(): void {
		$gatherpress_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertSame( array(), Event_Discussion::get_gallery( $gatherpress_post->ID ) );
	}

	/**
	 * Test sanitize_gallery_meta rejects invalid data.
	 *
	 * @covers ::sanitize_gallery_meta
	 *
	 * @return void
	 */
	public function test_sanitize_gallery_meta_invalid(): void {
		$gatherpress_instance = Event_Discussion::get_instance();

		// Non-JSON string should return empty.
		$this->assertSame( '', $gatherpress_instance->sanitize_gallery_meta( 'not-json' ) );

		// Non-array JSON should return empty.
		$this->assertSame( '', $gatherpress_instance->sanitize_gallery_meta( '"string"' ) );
	}
}
