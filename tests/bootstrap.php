<?php
/**
 * PHPUnit bootstrap for Convoca Assistant tests.
 *
 * @package Convoca\Assistant
 */

// Detect WordPress test environment.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test library not found.\n";
	echo "Set WP_TESTS_DIR or run: composer install && vendor/bin/install-wp-tests.sh\n";
	exit( 1 );
}

// Load WordPress test functions.
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/convoca-assistant.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start the WordPress test bootstrap.
require $wp_tests_dir . '/includes/bootstrap.php';
