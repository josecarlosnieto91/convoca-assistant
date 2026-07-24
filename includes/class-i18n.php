<?php
/**
 * I18n: handles translation loading.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Loads plugin text domain for internationalization.
 */
class I18n {

	/**
	 * Initialize translation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		// phpcs:ignore NeutronStandard.Functions.VariableFunctions.VariableFunction
		$load_fn = '\load_plugin_textdomain';
		$load_fn(
			'convoca-assistant',
			false,
			dirname( plugin_basename( CONVOCA_ASSISTANT_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get the .pot file path for reference.
	 *
	 * @return string
	 */
	public static function pot_file(): string {
		return CONVOCA_ASSISTANT_DIR . 'languages/convoca-assistant.pot';
	}
}
