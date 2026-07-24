<?php
/**
 * Plugin Name:       Convoca Assistant
 * Plugin URI:        https://getconvoca.app
 * Description:       Asistente conversacional local sin IA para WordPress. Busqueda difusa con Fuse.js sobre tu base de conocimiento. Sin APIs externas, sin cloud, compatible GDPR.
 * Version:           0.2.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://getconvoca.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-assistant
 * Domain Path:       /languages
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Composer autoload ─────────────────────────────── */
$convoca_assistant_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $convoca_assistant_autoload ) ) {
	require_once $convoca_assistant_autoload;
}

/* ── Constants ────────────────────────────────── */
if ( ! defined( 'CONVOCA_ASSISTANT_VERSION' ) ) {
	define( 'CONVOCA_ASSISTANT_VERSION', '0.2.1' );
}
if ( ! defined( 'CONVOCA_ASSISTANT_DB_VERSION' ) ) {
	define( 'CONVOCA_ASSISTANT_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'CONVOCA_ASSISTANT_FILE' ) ) {
	define( 'CONVOCA_ASSISTANT_FILE', __FILE__ );
}
if ( ! defined( 'CONVOCA_ASSISTANT_DIR' ) ) {
	define( 'CONVOCA_ASSISTANT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_ASSISTANT_URL' ) ) {
	define( 'CONVOCA_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_ASSISTANT_ASSETS_URL' ) ) {
	define( 'CONVOCA_ASSISTANT_ASSETS_URL', CONVOCA_ASSISTANT_URL . 'assets/' );
}
if ( ! defined( 'CONVOCA_ASSISTANT_INDEX_DIR' ) ) {
	$convoca_assistant_upload = wp_upload_dir();
	define( 'CONVOCA_ASSISTANT_INDEX_DIR', $convoca_assistant_upload['basedir'] . '/convoca-assistant/' );
}

/**
 * Bootstrap the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		// Load translations.
		add_action( 'init', __NAMESPACE__ . '\\load_plugin_textdomain' );

		// Initialize main classes.
		Knowledge_Base::init();
		Indexer::init();
		Widget::init();
		REST_Controller::init();
		Statistics::init();

		if ( is_admin() ) {
			Admin::init();
			Settings::init();
			Synonyms::init();
			Export_Import::init();
		}
	}
);

/**
 * Load plugin text domain.
 */
function load_plugin_textdomain(): void {
	$convoca_load = '\\load_plugin_textdomain';
	$convoca_load(
		'convoca-assistant',
		false,
		dirname( plugin_basename( CONVOCA_ASSISTANT_FILE ) ) . '/languages'
	);
}

/* ── Activation / Deactivation ────────────────────────────── */
register_activation_hook(
	CONVOCA_ASSISTANT_FILE,
	function (): void {
		Installer::activate();
	}
);

register_deactivation_hook(
	CONVOCA_ASSISTANT_FILE,
	function (): void {
		Installer::deactivate();
	}
);
