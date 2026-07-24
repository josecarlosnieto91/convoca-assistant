<?php
/**
 * Tests for the Installer class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Installer;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Installer
 */
class Test_Installer extends WP_UnitTestCase {

	/**
	 * Test default settings structure.
	 */
	public function test_default_settings(): void {
		$settings = Installer::default_settings();

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'widget_enabled', $settings );
		$this->assertArrayHasKey( 'search_mode', $settings );
		$this->assertArrayHasKey( 'log_enabled', $settings );
		$this->assertArrayHasKey( 'log_retention_days', $settings );
		$this->assertTrue( $settings['widget_enabled'] );
	}

	/**
	 * Test default stop words are not empty.
	 */
	public function test_default_stop_words(): void {
		$words = Installer::default_stop_words();

		$this->assertNotEmpty( $words );
		$this->assertContains( 'el', $words );
		$this->assertContains( 'de', $words );
		$this->assertContains( 'no', $words );
	}

	/**
	 * Test default settings have all required keys.
	 */
	public function test_default_settings_keys(): void {
		$settings = Installer::default_settings();

		$required = array(
			'widget_enabled',
			'widget_position',
			'widget_primary_color',
			'source_post',
			'source_page',
			'search_mode',
			'log_enabled',
			'log_retention_days',
			'maintenance_mode',
		);

		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $settings, "Missing required setting: {$key}" );
		}
	}
}
