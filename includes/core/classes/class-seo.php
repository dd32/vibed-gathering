<?php
/**
 * SEO enhancements for GatherPress.
 *
 * Handles structured data (JSON-LD) output for event pages, following
 * schema.org specifications for better search engine visibility and
 * rich snippet support.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Seo.
 *
 * Manages structured data output for event and venue pages.
 *
 * @since 1.0.0
 */
class Seo {
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
		add_action( 'wp_head', array( $this, 'output_structured_data' ) );
		add_action( 'wp_head', array( $this, 'output_open_graph_tags' ) );
	}

	/**
	 * Output JSON-LD structured data in the page head.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_structured_data(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_type = get_post_type();

		if ( Event::POST_TYPE === $post_type ) {
			$this->output_event_structured_data();
		}
	}

	/**
	 * Output Open Graph and Twitter Card meta tags for event pages.
	 *
	 * Enables rich social sharing previews when event URLs are shared
	 * on Facebook, Twitter/X, Mastodon, Slack, Discord, etc.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function output_open_graph_tags(): void {
		if ( ! is_singular( Event::POST_TYPE ) ) {
			return;
		}

		$gatherpress_post_id = get_the_ID();
		if ( ! $gatherpress_post_id ) {
			return;
		}

		$gatherpress_event     = new Event( $gatherpress_post_id );
		$gatherpress_title     = get_the_title( $gatherpress_post_id );
		$gatherpress_excerpt   = wp_strip_all_tags( get_the_excerpt( $gatherpress_post_id ) );
		$gatherpress_url       = get_the_permalink( $gatherpress_post_id );
		$gatherpress_thumbnail = get_the_post_thumbnail_url( $gatherpress_post_id, 'large' );
		$gatherpress_site_name = get_bloginfo( 'name' );
		$gatherpress_datetime  = $gatherpress_event->get_display_datetime();

		// Build description with date info.
		$gatherpress_description = $gatherpress_datetime;
		if ( ! empty( $gatherpress_excerpt ) ) {
			$gatherpress_description .= ' — ' . $gatherpress_excerpt;
		}

		// Open Graph tags.
		$gatherpress_tags = array(
			'og:type'        => 'event',
			'og:title'       => $gatherpress_title,
			'og:description' => mb_substr( $gatherpress_description, 0, 300 ),
			'og:url'         => $gatherpress_url,
			'og:site_name'   => $gatherpress_site_name,
		);

		if ( ! empty( $gatherpress_thumbnail ) ) {
			$gatherpress_tags['og:image'] = $gatherpress_thumbnail;
		}

		// Twitter Card tags.
		$gatherpress_tags['twitter:card']        = ! empty( $gatherpress_thumbnail ) ? 'summary_large_image' : 'summary';
		$gatherpress_tags['twitter:title']       = $gatherpress_title;
		$gatherpress_tags['twitter:description'] = mb_substr( $gatherpress_description, 0, 200 );

		/**
		 * Filters the Open Graph tags before output.
		 *
		 * @since 1.0.0
		 *
		 * @param array $gatherpress_tags The OG/Twitter tags.
		 * @param int   $gatherpress_post_id The event post ID.
		 */
		$gatherpress_tags = apply_filters( 'gatherpress_open_graph_tags', $gatherpress_tags, $gatherpress_post_id );

		foreach ( $gatherpress_tags as $gatherpress_property => $gatherpress_content ) {
			if ( empty( $gatherpress_content ) ) {
				continue;
			}

			// Use "name" attribute for twitter tags, "property" for OG.
			$gatherpress_attr = str_starts_with( $gatherpress_property, 'twitter:' ) ? 'name' : 'property';

			printf(
				'<meta %s="%s" content="%s" />' . "\n",
				esc_attr( $gatherpress_attr ),
				esc_attr( $gatherpress_property ),
				esc_attr( $gatherpress_content )
			);
		}
	}

	/**
	 * Output JSON-LD structured data for an event.
	 *
	 * Follows the schema.org/Event specification for rich results in Google,
	 * Bing, and other search engines.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function output_event_structured_data(): void {
		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return;
		}

		$event = new Event( $post_id );

		if ( ! $event->event ) {
			return;
		}

		$datetime = $event->get_datetime();

		if ( empty( $datetime['datetime_start_gmt'] ) ) {
			return;
		}

		$schema = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'Event',
			'name'      => get_the_title( $post_id ),
			'startDate' => $this->format_iso8601( $datetime['datetime_start_gmt'] ),
			'endDate'   => $this->format_iso8601( $datetime['datetime_end_gmt'] ),
		);

		// Add description.
		$excerpt = get_the_excerpt( $post_id );
		if ( ! empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// Add URL.
		$schema['url'] = get_the_permalink( $post_id );

		// Add featured image.
		$thumbnail = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $thumbnail ) {
			$schema['image'] = $thumbnail;
		}

		// Add venue/location.
		$venue_info = $event->get_venue_information();
		$schema     = $this->add_location_data( $schema, $venue_info, $event );

		// Add organizer.
		$author = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		if ( ! empty( $author ) ) {
			$schema['organizer'] = array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			);
		}

		// Add RSVP/attendance data.
		$rsvp = $event->rsvp;
		if ( $rsvp ) {
			$responses = $rsvp->responses();
			$attending = $responses['attending']['count'] ?? 0;

			if ( $attending > 0 ) {
				$schema['attendeeCount'] = $attending;
			}
		}

		// Add max attendance if set.
		$max_attendance = get_post_meta( $post_id, 'gatherpress_max_attendance_limit', true );
		if ( ! empty( $max_attendance ) && intval( $max_attendance ) > 0 ) {
			$schema['maximumAttendeeCapacity'] = intval( $max_attendance );
		}

		// Event is free unless we add payment support later.
		$schema['isAccessibleForFree'] = true;
		$schema['offers']              = array(
			'@type' => 'Offer',
			'price' => '0',
			'url'   => get_the_permalink( $post_id ),
		);

		// Add event status.
		$schema['eventStatus'] = 'https://schema.org/EventScheduled';

		/**
		 * Filters the event structured data before output.
		 *
		 * @since 1.0.0
		 *
		 * @param array $schema  The structured data array.
		 * @param int   $post_id The event post ID.
		 */
		$schema = apply_filters( 'gatherpress_event_structured_data', $schema, $post_id );

		$this->output_json_ld( $schema );
	}

	/**
	 * Add location data to the structured data schema.
	 *
	 * Determines whether the event is online, in-person, or mixed attendance
	 * and adds the appropriate location data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schema     The schema array.
	 * @param array $venue_info The venue information array.
	 * @param Event $event      The event object.
	 * @return array The updated schema array.
	 */
	private function add_location_data( array $schema, array $venue_info, Event $event ): array {
		$venue_name  = $venue_info['name'] ?? '';
		$online_link = get_post_meta( $event->event->ID, 'gatherpress_online_event_link', true );

		$has_venue  = ! empty( $venue_name ) && __( 'Online event', 'gatherpress' ) !== $venue_name;
		$has_online = ! empty( $online_link );

		if ( $has_venue && $has_online ) {
			$schema['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
		} elseif ( $has_online ) {
			$schema['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
		} else {
			$schema['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
		}

		// Add physical location.
		if ( $has_venue ) {
			$location = array(
				'@type' => 'Place',
				'name'  => $venue_name,
			);

			$full_address = $venue_info['full_address'] ?? '';
			if ( ! empty( $full_address ) ) {
				$location['address'] = array(
					'@type'          => 'PostalAddress',
					'streetAddress'  => $full_address,
				);
			}

			$schema['location'] = $location;
		}

		// Add virtual location.
		if ( $has_online ) {
			$virtual_location = array(
				'@type' => 'VirtualLocation',
				'url'   => $online_link,
			);

			if ( $has_venue ) {
				// Mixed attendance: use array of locations.
				$schema['location'] = array(
					$schema['location'],
					$virtual_location,
				);
			} else {
				$schema['location'] = $virtual_location;
			}
		}

		return $schema;
	}

	/**
	 * Format a datetime string to ISO 8601 format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime The datetime string in Y-m-d H:i:s format.
	 * @return string The ISO 8601 formatted datetime.
	 */
	private function format_iso8601( string $datetime ): string {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '';
		}

		// Input is in GMT, output ISO 8601 with Z suffix.
		$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, new \DateTimeZone( 'UTC' ) );

		if ( ! $dt ) {
			return '';
		}

		return $dt->format( 'c' );
	}

	/**
	 * Output a JSON-LD script tag.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The structured data to output.
	 * @return void
	 */
	private function output_json_ld( array $data ): void {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( ! $json ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD must not be HTML-escaped.
		);
	}
}
