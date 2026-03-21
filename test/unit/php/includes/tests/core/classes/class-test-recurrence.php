<?php
/**
 * Class handles unit tests for GatherPress\Core\Recurrence.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Recurrence;
use GatherPress\Tests\Base;

/**
 * Class Test_Recurrence.
 *
 * @coversDefaultClass \GatherPress\Core\Recurrence
 */
class Test_Recurrence extends Base {
	/**
	 * Test weekly occurrence calculation.
	 *
	 * @covers ::calculate_occurrences
	 *
	 * @return void
	 */
	public function test_calculate_occurrences_weekly(): void {
		$rule = array(
			'frequency'   => 'weekly',
			'day_of_week' => 2,
		);

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-03-03', 4 );

		$this->assertCount( 4, $occurrences );
		$this->assertSame( '2026-03-03', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-10', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-17', $occurrences[2]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-24', $occurrences[3]->format( 'Y-m-d' ) );
	}

	/**
	 * Test biweekly occurrence calculation.
	 *
	 * @covers ::calculate_occurrences
	 *
	 * @return void
	 */
	public function test_calculate_occurrences_biweekly(): void {
		$rule = array(
			'frequency'   => 'biweekly',
			'day_of_week' => 4,
		);

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-03-05', 3 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-03-05', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-19', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-04-02', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * Test monthly-date occurrence calculation.
	 *
	 * @covers ::calculate_occurrences
	 *
	 * @return void
	 */
	public function test_calculate_occurrences_monthly_date(): void {
		$rule = array(
			'frequency'    => 'monthly-date',
			'day_of_month' => 15,
		);

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-01-15', 3 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-01-15', $occurrences[0]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-02-15', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-15', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * Test monthly-date with day overflow (31st in February).
	 *
	 * @covers ::calculate_occurrences
	 *
	 * @return void
	 */
	public function test_calculate_occurrences_monthly_date_overflow(): void {
		$rule = array(
			'frequency'    => 'monthly-date',
			'day_of_month' => 31,
		);

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-01-31', 3 );

		$this->assertCount( 3, $occurrences );
		$this->assertSame( '2026-01-31', $occurrences[0]->format( 'Y-m-d' ) );
		// February doesn't have 31 days, should use last day.
		$this->assertSame( '2026-02-28', $occurrences[1]->format( 'Y-m-d' ) );
		$this->assertSame( '2026-03-31', $occurrences[2]->format( 'Y-m-d' ) );
	}

	/**
	 * Test occurrence calculation respects end_date.
	 *
	 * @covers ::calculate_occurrences
	 *
	 * @return void
	 */
	public function test_calculate_occurrences_with_end_date(): void {
		$rule = array(
			'frequency'   => 'weekly',
			'day_of_week' => 1,
			'end_date'    => '2026-03-20',
		);

		$occurrences = Recurrence::calculate_occurrences( $rule, '2026-03-02', 10 );

		// Should stop at or before 2026-03-20 even though we asked for 10.
		$this->assertLessThanOrEqual( 3, count( $occurrences ) );

		foreach ( $occurrences as $occurrence ) {
			$this->assertLessThanOrEqual( '2026-03-20', $occurrence->format( 'Y-m-d' ) );
		}
	}

	/**
	 * Test is_recurring returns false for events without a rule.
	 *
	 * @covers ::is_recurring
	 *
	 * @return void
	 */
	public function test_is_recurring_false_for_non_recurring(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		$this->assertFalse( Recurrence::is_recurring( $post->ID ) );
	}

	/**
	 * Test save_rule validates frequency.
	 *
	 * @covers ::save_rule
	 *
	 * @return void
	 */
	public function test_save_rule_rejects_invalid_frequency(): void {
		$post   = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$result = Recurrence::save_rule( $post->ID, array( 'frequency' => 'invalid' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test save_rule and get_rule roundtrip.
	 *
	 * @covers ::save_rule
	 * @covers ::get_rule
	 *
	 * @return void
	 */
	public function test_save_and_get_rule(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$rule = array(
			'frequency'   => 'weekly',
			'day_of_week' => 3,
		);

		$result = Recurrence::save_rule( $post->ID, $rule );
		$this->assertTrue( $result );

		$saved_rule = Recurrence::get_rule( $post->ID );
		$this->assertSame( 'weekly', $saved_rule['frequency'] );
		$this->assertSame( 3, $saved_rule['day_of_week'] );
		$this->assertTrue( Recurrence::is_recurring( $post->ID ) );
	}

	/**
	 * Test get_description returns human-readable strings.
	 *
	 * @covers ::get_description
	 *
	 * @return void
	 */
	public function test_get_description(): void {
		$this->assertStringContainsString(
			'Every',
			Recurrence::get_description(
				array(
					'frequency'   => 'weekly',
					'day_of_week' => 2,
				)
			)
		);

		$this->assertStringContainsString(
			'other',
			Recurrence::get_description(
				array(
					'frequency'   => 'biweekly',
					'day_of_week' => 4,
				)
			)
		);

		$this->assertStringContainsString(
			'every month',
			Recurrence::get_description(
				array(
					'frequency'    => 'monthly-date',
					'day_of_month' => 15,
				)
			)
		);
	}
}
