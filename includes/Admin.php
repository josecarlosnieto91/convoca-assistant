<?php
/**
 * Admin: main admin menu and page rendering.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Creates the admin menu pages and enqueues admin assets.
 */
class Admin {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		// Main menu page.
		add_menu_page(
			__( 'Convoca Assistant', 'convoca-assistant' ),
			__( 'Convoca Assistant', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-format-chat',
			40
		);

		// Submenu: Dashboard.
		add_submenu_page(
			'convoca-assistant',
			__( 'Dashboard', 'convoca-assistant' ),
			__( 'Dashboard', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant',
			array( __CLASS__, 'render_dashboard' )
		);

		// Submenu: Knowledge.
		add_submenu_page(
			'convoca-assistant',
			__( 'Conocimiento', 'convoca-assistant' ),
			__( 'Conocimiento', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-knowledge',
			array( __CLASS__, 'render_knowledge' )
		);

		// Submenu: Synonyms.
		add_submenu_page(
			'convoca-assistant',
			__( 'Sinónimos', 'convoca-assistant' ),
			__( 'Sinónimos', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-synonyms',
			array( __CLASS__, 'render_synonyms' )
		);

		// Submenu: Analytics.
		add_submenu_page(
			'convoca-assistant',
			__( 'Analytics', 'convoca-assistant' ),
			__( 'Analytics', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-analytics',
			array( __CLASS__, 'render_analytics' )
		);

		// Submenu: Unanswered.
		add_submenu_page(
			'convoca-assistant',
			__( 'Sin respuesta', 'convoca-assistant' ),
			__( 'Sin respuesta', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-unanswered',
			array( __CLASS__, 'render_unanswered' )
		);

		// Submenu: Settings.
		add_submenu_page(
			'convoca-assistant',
			__( 'Ajustes', 'convoca-assistant' ),
			__( 'Ajustes', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-settings',
			array( __CLASS__, 'render_settings' )
		);

		// Submenu: Tools.
		add_submenu_page(
			'convoca-assistant',
			__( 'Herramientas', 'convoca-assistant' ),
			__( 'Herramientas', 'convoca-assistant' ),
			'manage_options',
			'convoca-assistant-tools',
			array( __CLASS__, 'render_tools' )
		);
	}

	/**
	 * Enqueue admin-side scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'convoca-assistant' ) ) {
			return;
		}

		wp_enqueue_style(
			'convoca-assistant-admin',
			CONVOCA_ASSISTANT_ASSETS_URL . 'css/admin-assistant.css',
			array(),
			CONVOCA_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			'convoca-assistant-admin',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/admin-assistant.js',
			array(),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		wp_localize_script(
			'convoca-assistant-admin',
			'convocaAdmin',
			array(
				'restUrl' => rest_url( 'convoca/v1/assistant/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'createFaqUrl' => admin_url( 'post-new.php?post_type=convoca_faq' ),
				'indexStats' => Indexer::get_stats(),
			)
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-dashboard.php';
	}

	/**
	 * Render the Knowledge page.
	 *
	 * @return void
	 */
	public static function render_knowledge(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-knowledge.php';
	}

	/**
	 * Render the Synonyms page.
	 *
	 * @return void
	 */
	public static function render_synonyms(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-synonyms.php';
	}

	/**
	 * Render the Analytics page.
	 *
	 * @return void
	 */
	public static function render_analytics(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-analytics.php';
	}

	/**
	 * Render the Unanswered page.
	 *
	 * @return void
	 */
	public static function render_unanswered(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-unanswered.php';
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public static function render_settings(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-settings.php';
	}

	/**
	 * Render the Tools page.
	 *
	 * @return void
	 */
	public static function render_tools(): void {
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/admin-tools.php';
	}
}
