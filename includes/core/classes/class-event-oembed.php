<?php
/**
 * OEmbed support for GatherPress events.
 *
 * Enables rich embedding of event pages when URLs are pasted into
 * WordPress posts or other oEmbed-compatible platforms.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Event_Oembed.
 *
 * Enhances the default WordPress oEmbed response for GatherPress events.
 *
 * @since 1.0.0
 */
class Event_Oembed {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'oembed_response_data', array( $this, 'enhance_oembed_response' ), 10, 4 );
		add_filter( 'embed_html', array( $this, 'customize_embed_html' ), 10, 4 );
	}

	/**
	 * Enhance the oEmbed response data for events.
	 *
	 * Adds event-specific metadata to the oEmbed JSON response,
	 * including datetime, venue, and RSVP count.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $gatherpress_data The oEmbed response data.
	 * @param \WP_Post $gatherpress_post The post object.
	 * @param int      $gatherpress_width The requested width.
	 * @param int      $gatherpress_height The requested height.
	 * @return array The enhanced response data.
	 */
	public function enhance_oembed_response( array $gatherpress_data, \WP_Post $gatherpress_post, int $gatherpress_width, int $gatherpress_height ): array {
		if ( Event::POST_TYPE !== $gatherpress_post->post_type ) {
			return $gatherpress_data;
		}

		$gatherpress_event = new Event( $gatherpress_post->ID );
		$gatherpress_venue = $gatherpress_event->get_venue_information();

		// Add event-specific data.
		$gatherpress_data['gatherpress_datetime'] = $gatherpress_event->get_display_datetime();
		$gatherpress_data['gatherpress_venue']    = $gatherpress_venue['name'] ?? '';

		// Enhance the title with the date.
		$gatherpress_data['title'] = sprintf(
			'%s — %s',
			$gatherpress_data['title'],
			$gatherpress_event->get_display_datetime()
		);

		return $gatherpress_data;
	}

	/**
	 * Customize the embed HTML template for events.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $gatherpress_output The embed HTML output.
	 * @param \WP_Post $gatherpress_post   The post object.
	 * @param int      $gatherpress_width  The embed width.
	 * @param int      $gatherpress_height The embed height.
	 * @return string The customized embed HTML.
	 */
	public function customize_embed_html( string $gatherpress_output, \WP_Post $gatherpress_post, int $gatherpress_width, int $gatherpress_height ): string {
		if ( Event::POST_TYPE !== $gatherpress_post->post_type ) {
			return $gatherpress_output;
		}

		// WordPress's default embed template already includes title, excerpt, and site name.
		// We add event-specific data via the oembed_response_data filter above.
		return $gatherpress_output;
	}
}
