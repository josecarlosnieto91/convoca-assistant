<?php
/**
 * Plugin activation/deactivation handler.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Handles plugin lifecycle events.
 */
class Installer {

	/**
	 * Get default settings.
	 */
	public static function default_settings(): array {
		return array(
			'widget_title'          => 'Asistente Virtual',
			'widget_greeting'       => '¡Hola! Soy el asistente virtual. ¿En qué puedo ayudarte?',
			'widget_primary_color'  => '#2563eb',
			'widget_position'       => 'bottom-right',
			'maintenance_mode'      => false,
			'maintenance_message'   => '',
			'log_retention_days'    => 90,
			'enable_analytics'      => true,
			'index_post_types'      => array( 'post', 'page', 'convoca_faq', 'convoca_kb' ),
			'fuse_threshold'        => 0.4,
			'fuse_distance'         => 100,
			'session_window_minutes'=> 10,
		);
	}

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::db_init();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Initialize database tables if needed.
	 */
	private static function db_init(): void {
		// Log table.
		$table = self::log_table();
		if ( ! self::table_exists( $table ) ) {
			self::create_log_table( $table );
		}

		// Settings defaults.
		if ( ! get_option( 'convoca_assistant_settings' ) ) {
			add_option( 'convoca_assistant_settings', array(
				'widget_title'       => 'Asistente Virtual',
				'widget_greeting'    => '¡Hola! Soy el asistente virtual. ¿En qué puedo ayudarte?',
				'widget_primary_color' => '#2563eb',
				'widget_position'    => 'bottom-right',
			) );
		}

		// Synonyms defaults.
		if ( ! get_option( 'convoca_assistant_synonyms' ) ) {
			add_option( 'convoca_assistant_synonyms', array() );
		}
	}

	/**
	 * Get the log table name.
	 */
	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'convoca_assistant_log';
	}

	/**
	 * Check if a table exists.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create the log table.
	 */
	private static function create_log_table( string $table ): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			query TEXT NOT NULL,
			response TEXT,
			answered TINYINT(1) DEFAULT 0,
			results_count INT DEFAULT 0,
			time_ms FLOAT DEFAULT 0,
			user_ip VARCHAR(64) DEFAULT '',
			user_agent VARCHAR(255) DEFAULT '',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY answered (answered),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
