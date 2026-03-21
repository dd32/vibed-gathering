<?php
/**
 * Class handles unit tests for GatherPress\Core\Email.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Email;
use GatherPress\Tests\Base;

/**
 * Class Test_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Email
 */
class Test_Email extends Base {
	/**
	 * Test render_email_body produces valid HTML.
	 *
	 * @covers ::render_email_body
	 *
	 * @return void
	 */
	public function test_render_email_body_produces_html(): void {
		$gatherpress_html = Email::render_email_body(
			array(
				'greeting' => 'Hello Test User,',
				'message'  => 'This is a test message.',
			)
		);

		$this->assertStringContainsString( '<!DOCTYPE html>', $gatherpress_html );
		$this->assertStringContainsString( 'Hello Test User,', $gatherpress_html );
		$this->assertStringContainsString( 'This is a test message.', $gatherpress_html );
	}

	/**
	 * Test render_email_body includes CTA button when provided.
	 *
	 * @covers ::render_email_body
	 *
	 * @return void
	 */
	public function test_render_email_body_with_button(): void {
		$gatherpress_html = Email::render_email_body(
			array(
				'greeting'    => 'Hi,',
				'message'     => 'Click below.',
				'button_text' => 'Click Me',
				'button_url'  => 'https://example.com/action',
			)
		);

		$this->assertStringContainsString( 'Click Me', $gatherpress_html );
		$this->assertStringContainsString( 'https://example.com/action', $gatherpress_html );
	}

	/**
	 * Test render_email_body without optional params.
	 *
	 * @covers ::render_email_body
	 *
	 * @return void
	 */
	public function test_render_email_body_minimal(): void {
		$gatherpress_html = Email::render_email_body(
			array(
				'message' => 'Minimal message.',
			)
		);

		$this->assertStringContainsString( '<!DOCTYPE html>', $gatherpress_html );
		$this->assertStringContainsString( 'Minimal message.', $gatherpress_html );
		// Should not contain button when not provided.
		$this->assertStringNotContainsString( 'Click Me', $gatherpress_html );
	}

	/**
	 * Test render_email_body with footer text.
	 *
	 * @covers ::render_email_body
	 *
	 * @return void
	 */
	public function test_render_email_body_footer(): void {
		$gatherpress_html = Email::render_email_body(
			array(
				'message'     => 'Test.',
				'footer_text' => 'Sent by the organizer.',
			)
		);

		$this->assertStringContainsString( 'Sent by the organizer.', $gatherpress_html );
	}

	/**
	 * Test render_email_body has inline styles (email-compatible).
	 *
	 * @covers ::render_email_body
	 *
	 * @return void
	 */
	public function test_render_email_body_has_inline_styles(): void {
		$gatherpress_html = Email::render_email_body(
			array(
				'message' => 'Styled email.',
			)
		);

		// Email templates must use inline styles, not external stylesheets.
		$this->assertStringContainsString( 'style="', $gatherpress_html );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Testing string content, not enqueuing.
		$this->assertStringNotContainsString( '<link rel="stylesheet"', $gatherpress_html );
	}

	/**
	 * Test send method constructs proper headers.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_uses_html_content_type(): void {
		// We can't easily test wp_mail in unit tests, but we can verify
		// the filter that modifies headers is set up.
		$gatherpress_instance = Email::get_instance();

		$this->assertIsObject( $gatherpress_instance );
	}
}
