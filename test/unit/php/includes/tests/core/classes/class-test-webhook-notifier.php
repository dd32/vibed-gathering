<?php
/**
 * Class handles unit tests for GatherPress\Core\Webhook_Notifier.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Webhook_Notifier;
use GatherPress\Tests\Base;

/**
 * Class Test_Webhook_Notifier.
 *
 * @coversDefaultClass \GatherPress\Core\Webhook_Notifier
 */
class Test_Webhook_Notifier extends Base {
	/**
	 * Test OPTION_WEBHOOK_URL constant.
	 *
	 * @return void
	 */
	public function test_option_constant(): void {
		$this->assertSame( 'gatherpress_webhook_url', Webhook_Notifier::OPTION_WEBHOOK_URL );
	}

	/**
	 * Test get_webhook_url returns empty when not configured.
	 *
	 * @covers ::get_webhook_url
	 *
	 * @return void
	 */
	public function test_get_webhook_url_empty(): void {
		$this->assertSame( '', Webhook_Notifier::get_webhook_url() );
	}

	/**
	 * Test get_webhook_url returns configured URL.
	 *
	 * @covers ::get_webhook_url
	 *
	 * @return void
	 */
	public function test_get_webhook_url_configured(): void {
		$gatherpress_url = 'https://hooks.slack.com/services/T00/B00/xxx';
		update_option( Webhook_Notifier::OPTION_WEBHOOK_URL, $gatherpress_url );

		$this->assertSame( $gatherpress_url, Webhook_Notifier::get_webhook_url() );
	}

	/**
	 * Test hooks are registered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$gatherpress_instance = Webhook_Notifier::get_instance();

		$this->assertGreaterThan(
			0,
			has_action( 'gatherpress_event_cancelled', array( $gatherpress_instance, 'on_event_cancelled' ) )
		);
		$this->assertGreaterThan(
			0,
			has_action( 'transition_post_status', array( $gatherpress_instance, 'on_event_published' ) )
		);
	}
}
