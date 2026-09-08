<?php
/**
 * Unit tests for Convoca Assistant Settings (option parsing).
 *
 * Verifies that the new FASE 5 settings (direct_threshold and the ranking
 * weights weights_*) are declared with the correct defaults and parsed as
 * floats by Settings::sanitize().
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Installer;
use Convoca\Assistant\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Settings
 */
class SettingsTest extends TestCase {

	/**
	 * Reset the mocked option store before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_assistant_options'] = array();
	}

	/**
	 * Defaults include direct_threshold (0.55) and the ranking weights.
	 */
	public function test_defaults_include_direct_threshold_and_weights(): void {
		$defaults = Installer::default_settings();

		$this->assertSame( 0.55, $defaults['direct_threshold'] );
		$this->assertSame( 0.45, $defaults['weights_fuzzy'] );
		$this->assertSame( 0.10, $defaults['weights_graph'] );
		$this->assertSame( 0.15, $defaults['weights_exact'] );
		$this->assertSame( 0.15, $defaults['weights_exact_title'] );
	}

	/**
	 * get_all() merges the new defaults even with no stored settings.
	 */
	public function test_get_all_merges_new_defaults(): void {
		$all = Settings::get_all();

		$this->assertSame( 0.55, $all['direct_threshold'] );
		$this->assertSame( 0.45, $all['weights_fuzzy'] );
	}

	/**
	 * sanitize() casts the new settings to float.
	 */
	public function test_sanitize_parses_direct_threshold_and_weights_as_float(): void {
		$sanitized = Settings::sanitize(
			array(
				'direct_threshold'    => '0.70',
				'weights_fuzzy'       => '0.5',
				'weights_graph'       => '0.2',
				'weights_exact'       => '0.25',
				'weights_exact_title' => '0.35',
			)
		);

		$this->assertSame( 0.70, $sanitized['direct_threshold'] );
		$this->assertSame( 0.5, $sanitized['weights_fuzzy'] );
		$this->assertSame( 0.2, $sanitized['weights_graph'] );
		$this->assertSame( 0.25, $sanitized['weights_exact'] );
		$this->assertSame( 0.35, $sanitized['weights_exact_title'] );
	}

	/**
	 * sanitize() keeps defaults when the new keys are absent.
	 */
	public function test_sanitize_defaults_when_keys_absent(): void {
		$sanitized = Settings::sanitize( array() );

		$this->assertSame( 0.55, $sanitized['direct_threshold'] );
		$this->assertSame( 0.45, $sanitized['weights_fuzzy'] );
		$this->assertSame( 0.15, $sanitized['weights_exact_title'] );
	}
}
