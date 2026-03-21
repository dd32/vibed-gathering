<?php
/**
 * Class handles unit tests for GatherPress\Core\Meetup_Import.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Meetup_Import;
use GatherPress\Tests\Base;

/**
 * Class Test_Meetup_Import.
 *
 * @coversDefaultClass \GatherPress\Core\Meetup_Import
 */
class Test_Meetup_Import extends Base {
	/**
	 * Test import_from_json rejects invalid JSON.
	 *
	 * @covers ::import_from_json
	 *
	 * @return void
	 */
	public function test_import_from_json_rejects_invalid_json(): void {
		$gatherpress_instance = Meetup_Import::get_instance();
		$gatherpress_result   = $gatherpress_instance->import_from_json( 'not valid json' );

		$this->assertInstanceOf( \WP_Error::class, $gatherpress_result );
	}

	/**
	 * Test import_from_json imports a valid event.
	 *
	 * @covers ::import_from_json
	 *
	 * @return void
	 */
	public function test_import_from_json_valid_event(): void {
		$gatherpress_instance = Meetup_Import::get_instance();
		$gatherpress_json     = wp_json_encode(
			array(
				array(
					'name'     => 'WordPress Meetup March',
					'time'     => 1711900800000,
					'duration' => 7200000,
					'link'     => 'https://meetup.com/example/events/123',
				),
			)
		);

		$gatherpress_result = $gatherpress_instance->import_from_json( $gatherpress_json );

		$this->assertSame( 1, $gatherpress_result );
	}

	/**
	 * Test import_from_json handles single event object (not array).
	 *
	 * @covers ::import_from_json
	 *
	 * @return void
	 */
	public function test_import_from_json_single_object(): void {
		$gatherpress_instance = Meetup_Import::get_instance();
		$gatherpress_json     = wp_json_encode(
			array(
				'name' => 'Single Event Test',
				'time' => 1711900800000,
			)
		);

		$gatherpress_result = $gatherpress_instance->import_from_json( $gatherpress_json );

		$this->assertSame( 1, $gatherpress_result );
	}

	/**
	 * Test import skips events without required fields.
	 *
	 * @covers ::import_from_json
	 *
	 * @return void
	 */
	public function test_import_from_json_skips_invalid_events(): void {
		$gatherpress_instance = Meetup_Import::get_instance();
		$gatherpress_json     = wp_json_encode(
			array(
				array( 'name' => 'No Time Event' ),
				array( 'time' => 1711900800000 ),
			)
		);

		// Both should fail: one missing time, one missing name.
		$gatherpress_result = $gatherpress_instance->import_from_json( $gatherpress_json );

		$this->assertSame( 0, $gatherpress_result );
	}

	/**
	 * Test that imported events store original Meetup URL.
	 *
	 * @covers ::import_from_json
	 *
	 * @return void
	 */
	public function test_import_stores_meetup_url(): void {
		$gatherpress_instance = Meetup_Import::get_instance();
		$gatherpress_url      = 'https://meetup.com/wordpress-melb/events/456';
		$gatherpress_json     = wp_json_encode(
			array(
				'name' => 'URL Test Event',
				'time' => 1711900800000,
				'link' => $gatherpress_url,
			)
		);

		$gatherpress_instance->import_from_json( $gatherpress_json );

		// Find the imported event.
		$gatherpress_events = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'title'          => 'URL Test Event',
				'posts_per_page' => 1,
			)
		);

		$this->assertNotEmpty( $gatherpress_events );
		$this->assertSame(
			$gatherpress_url,
			get_post_meta( $gatherpress_events[0]->ID, 'gatherpress_meetup_original_url', true )
		);
	}
}
