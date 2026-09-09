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
			'widget_title'            => 'Asistente Virtual',
			'widget_greeting'         => __( '¡Hola! Soy el asistente virtual. ¿En qué puedo ayudarte?', 'convoca-assistant' ),
			'widget_primary_color'    => '#2563eb',
			'widget_position'         => 'bottom-right',
			'widget_enabled'          => true,
			'maintenance_mode'        => false,
			'maintenance_message'     => '',
			'log_retention_days'      => 90,
			'enable_analytics'        => true,
			'index_post_types'        => array( 'post', 'page', 'convoca_faq', 'convoca_kb' ),
			'index_max_content'       => 5000,
			'source_post'             => true,
			'source_page'             => true,
			'source_convoca_faq'      => true,
			'source_convoca_kb'       => true,
			'source_woocommerce'      => false,
			'weight_convoca_faq'      => 2.0,
			'weight_convoca_kb'       => 1.5,
			'weight_post'             => 1.0,
			'weight_page'             => 1.0,
			'weight_product'          => 1.0,
			'priority_types'          => array( 'convoca_faq', 'convoca_kb' ),
			'priority_boost'          => 1.35,
			// Umbral de respuesta directa (fuente prioritaria con score >= umbral).
			'direct_threshold'        => 0.55,
			// Pesos del ranking del buscador (composite score, server y mirror cliente).
			'weights_fuzzy'           => 0.45,
			'weights_graph'           => 0.10,
			'weights_exact'           => 0.15,
			'weights_exact_title'     => 0.15,
			'answer_max_length'       => 600,
			'answer_use_excerpt'      => true,
			'search_mode'             => 'client',
			'search_fallback'         => true,
			'search_max_results'      => 10,
			'search_threshold'        => 0.10,
			'search_fuse_threshold'   => 0.4,
			'search_fuse_distance'    => 100,
			'session_window_minutes'  => 10,
			'index_auto_regenerate'   => true,
			'index_compress'          => false,
			'log_enabled'             => true,
			'log_anonymous'           => true,
			'debug_mode'              => false,
			'widget_auto_open'        => 'never',
			'widget_auto_open_scroll' => 50,
			// Contacto fallback sin match (decisión 2026-09-09): widget «¿Hablamos?».
			'contact_email'           => '',
			'contact_phone'           => '',
			'contact_whatsapp'        => '',
		);
	}

	/**
	 * Get default stop words.
	 */
	public static function default_stop_words(): array {
		return array(
			'a',
			'al',
			'algo',
			'alas',
			'ambos',
			'ante',
			'aquel',
			'aquellos',
			'aqui',
			'asi',
			'aunque',
			'bajo',
			'bastante',
			'bien',
			'cada',
			'casi',
			'como',
			'con',
			'cual',
			'cualquier',
			'cuando',
			'de',
			'del',
			'demas',
			'desde',
			'donde',
			'dos',
			'durante',
			'e',
			'el',
			'ella',
			'ellas',
			'ellos',
			'en',
			'entre',
			'era',
			'eran',
			'es',
			'esa',
			'esas',
			'ese',
			'eso',
			'esos',
			'esta',
			'estaba',
			'estan',
			'estas',
			'este',
			'esto',
			'etc',
			'fue',
			'gracias',
			'ha',
			'hace',
			'hacen',
			'han',
			'has',
			'hasta',
			'hay',
			'la',
			'las',
			'le',
			'les',
			'lo',
			'los',
			'mas',
			'me',
			'menos',
			'mi',
			'mis',
			'mucha',
			'muchas',
			'mucho',
			'muchos',
			'muy',
			'nada',
			'ni',
			'ningun',
			'no',
			'nos',
			'nosotras',
			'nosotros',
			'nuestra',
			'nuestro',
			'o',
			'os',
			'otra',
			'otro',
			'para',
			'pero',
			'poco',
			'podemos',
			'por',
			'porque',
			'que',
			'quien',
			's',
			'se',
			'segun',
			'ser',
			'si',
			'sido',
			'sin',
			'sobre',
			'solo',
			'son',
			'su',
			'sus',
			'tambien',
			'tampoco',
			'tan',
			'tanto',
			'te',
			'tenemos',
			'tengo',
			'ti',
			'tiene',
			'tienen',
			'todo',
			'todos',
			'tu',
			'tus',
			'un',
			'una',
			'uno',
			'unos',
			'usted',
			'va',
			'van',
			'vosotras',
			'vosotros',
			'vuestra',
			'vuestro',
			'y',
			'ya',
			'yo',
		);
	}

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::db_init();
		flush_rewrite_rules();

		// Regenerate index on activation so the assistant has content immediately.
		if ( class_exists( 'Convoca\\Assistant\\Indexer' ) ) {
			try {
				Indexer::regenerate();
			} catch ( \Throwable $e ) {
				// Falla silenciosa — el índice se puede reconstruir desde admin.
				error_log( 'Convoca Assistant: index regeneration failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
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
			add_option(
				'convoca_assistant_settings',
				array(
					'widget_title'         => 'Asistente Virtual',
					'widget_greeting'      => __( '¡Hola! Soy el asistente virtual. ¿En qué puedo ayudarte?', 'convoca-assistant' ),
					'widget_primary_color' => '#2563eb',
					'widget_position'      => 'bottom-right',
				)
			);
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
	 *
	 * @param string $table Table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create the log table.
	 *
	 * @param string $table Table name.
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
