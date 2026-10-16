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
		add_action( 'admin_notices', array( __CLASS__, 'aviso_sin_respuesta' ) );
		add_action( 'admin_post_convoca_assistant_instalar_arranque', array( __CLASS__, 'handle_instalar_arranque' ) );
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
			__( 'Resumen', 'convoca-assistant' ),
			__( 'Resumen', 'convoca-assistant' ),
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
			__( 'Estadísticas', 'convoca-assistant' ),
			__( 'Estadísticas', 'convoca-assistant' ),
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
			'convocaAssistant',
			array(
				'restUrl'      => rest_url( 'convoca/v1/assistant/' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'createFaqUrl' => admin_url( 'post-new.php?post_type=convoca_faq' ),
				'indexStats'   => Indexer::get_stats(),
			)
		);
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	/**
	 * El aviso del panel: lo que el asistente no supo contestar, con su enlace.
	 *
	 * Sin esto, la pantalla «Sin respuesta» se queda olvidada —y era exactamente lo que pasaba: cinco
	 * consultas esperando desde hacía semanas (assistant#12). Solo sale en las pantallas del asistente, con
	 * el número de la semana y un botón para instalar el paquete de arranque mientras no esté puesto.
	 */
	public static function aviso_sin_respuesta(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pantalla = get_current_screen();
		if ( ! $pantalla || false === strpos( (string) $pantalla->id, 'convoca-assistant' ) ) {
			return;
		}

		$sin_respuesta = Statistics::unanswered_this_week();
		$instalado     = Starter_Pack::esta_instalado();

		if ( $sin_respuesta < 1 && $instalado ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Convoca Assistant', 'convoca-assistant' ) . '</strong> — ';

		if ( $sin_respuesta > 0 ) {
			echo esc_html(
				sprintf(
					/* translators: %d: número de consultas sin respuesta esta semana. */
					_n(
						'hay %d consulta sin respuesta esta semana.',
						'hay %d consultas sin respuesta esta semana.',
						$sin_respuesta,
						'convoca-assistant'
					),
					(int) $sin_respuesta
				)
			);
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=convoca-assistant-unanswered' ) ) . '">'
				. esc_html__( 'Verlas y responderlas', 'convoca-assistant' ) . '</a>';
		} else {
			esc_html_e( 'no hay consultas sin respuesta esta semana.', 'convoca-assistant' );
		}

		if ( ! $instalado ) {
			echo ' · ' . esc_html__( 'Todavía no tiene preguntas de arranque:', 'convoca-assistant' ) . ' ';
			printf(
				'<a class="button button-secondary" href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_assistant_instalar_arranque' ), 'convoca_assistant_arranque' ) ),
				esc_html__( 'Instalar el paquete de arranque', 'convoca-assistant' )
			);
		}

		echo '</p></div>';
	}

	/**
	 * Instala el paquete de arranque desde el aviso del panel.
	 */
	public static function handle_instalar_arranque(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'convoca_assistant_arranque' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'convoca-assistant' ) );
		}

		$resultado = Starter_Pack::instalar();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'convoca-assistant-knowledge',
					'arranque'  => (int) $resultado['fichas'],
					'sinonimos' => (int) $resultado['sinonimos'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the Dashboard page.
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
