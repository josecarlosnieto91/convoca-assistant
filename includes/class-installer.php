<?php
/**
 * Installer: activation, deactivation, and database setup.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

use Convoca\Core\Logger;

/**
 * Handles plugin activation/deactivation lifecycle and DB schema.
 */
class Installer {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::schedule_crons();

		// Set default options.
		if ( false === get_option( 'convoca_assistant_settings' ) ) {
			add_option( 'convoca_assistant_settings', self::default_settings(), '', false );
		}
		if ( false === get_option( 'convoca_assistant_synonyms' ) ) {
			add_option( 'convoca_assistant_synonyms', array(), '', false );
		}
		if ( false === get_option( 'convoca_assistant_stop_words' ) ) {
			add_option( 'convoca_assistant_stop_words', self::default_stop_words(), '', false );
		}

		add_option( 'convoca_assistant_db_version', CONVOCA_ASSISTANT_DB_VERSION, '', false );
		add_option( 'convoca_assistant_index_schema', 1, '', false );

		// Create index directory.
		$index_dir = CONVOCA_ASSISTANT_INDEX_DIR;
		if ( ! is_dir( $index_dir ) ) {
			wp_mkdir_p( $index_dir );
		}

		Logger::info( 'Convoca Assistant activated.', 'convoca-assistant' );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'convoca_assistant_regenerate' );
		wp_clear_scheduled_hook( 'convoca_assistant_log_cleanup' );

		Logger::info( 'Convoca Assistant deactivated.', 'convoca-assistant' );
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'convoca_assistant_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			query text NOT NULL,
			response_id bigint(20) UNSIGNED DEFAULT NULL,
			response_found tinyint(1) NOT NULL DEFAULT 0,
			score float DEFAULT NULL,
			clicked tinyint(1) NOT NULL DEFAULT 0,
			query_time_ms int(10) UNSIGNED DEFAULT NULL,
			page_url varchar(512) DEFAULT NULL,
			user_agent_hash varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY response_found (response_found),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Schedule recurring cron jobs.
	 *
	 * @return void
	 */
	private static function schedule_crons(): void {
		if ( ! wp_next_scheduled( 'convoca_assistant_regenerate' ) ) {
			wp_schedule_event( time(), 'every_5_minutes', 'convoca_assistant_regenerate' );
		}
		if ( ! wp_next_scheduled( 'convoca_assistant_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_assistant_log_cleanup' );
		}
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			// Widget.
			'widget_enabled'        => true,
			'widget_position'       => 'bottom-right',
			'widget_primary_color'  => '#2563eb',
			'widget_title'          => __( 'Asistente Virtual', 'convoca-assistant' ),
			'widget_greeting'       => __( '¡Hola! Soy el asistente virtual. ¿En qué puedo ayudarte?', 'convoca-assistant' ),
			'widget_auto_open'      => 'never',
			'widget_auto_open_scroll' => 50,

			// Sources.
			'source_post'           => true,
			'source_page'           => true,
			'source_convoca_faq'    => true,
			'source_convoca_kb'     => true,
			'source_woocommerce'    => false,

			// Weights.
			'weight_convoca_faq'    => 2.0,
			'weight_convoca_kb'     => 1.5,
			'weight_post'           => 1.0,
			'weight_page'           => 1.0,
			'weight_product'        => 0.8,

			// Search.
			'search_mode'           => 'client',
			'search_fallback'       => true,
			'search_max_results'    => 10,
			'search_threshold'      => 0.15,
			'search_fuse_threshold' => 0.4,
			'search_fuse_distance'  => 100,

			// Index.
			'index_auto_regenerate' => true,
			'index_compress'        => true,
			'index_max_content'     => 5000,

			// Privacy.
			'log_retention_days'    => 90,
			'log_anonymous'         => true,
			'log_enabled'           => true,

			// Maintenance.
			'maintenance_mode'      => false,
			'maintenance_message'   => __( 'El asistente está en mantenimiento. Vuelve pronto.', 'convoca-assistant' ),

			// Debug.
			'debug_mode'            => false,
		);
	}

	/**
	 * Default Spanish stop words.
	 *
	 * @return string[]
	 */
	public static function default_stop_words(): array {
		return array(
			'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
			'y', 'e', 'o', 'u', 'pero', 'sino',
			'de', 'del', 'en', 'para', 'por', 'con', 'sin', 'sobre',
			'a', 'ante', 'bajo', 'cabe', 'contra', 'desde', 'durante',
			'entre', 'hacia', 'hasta', 'mediante', 'tras', 'via',
			'más', 'menos', 'muy', 'tan', 'tanto',
			'es', 'son', 'fue', 'era', 'ser', 'estar', 'está', 'están',
			'no', 'si', 'se', 'que', 'lo', 'le', 'les', 'su', 'sus',
			'como', 'tal', 'cada', 'todo', 'toda', 'este', 'esta',
			'al', 'ello', 'eso', 'esa', 'esos', 'esas',
		);
	}
}
