<?php
/**
 * Export Import: handles knowledge base and settings export/import.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Allows exporting and importing the full knowledge base,
 * synonym list, and plugin configuration as JSON files.
 * Includes file validation, size limits, and version checks.
 */
class Export_Import {

	/** Max import file size (5 MB). */
	private const MAX_IMPORT_SIZE = 5 * 1024 * 1024;

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_convoca_assistant_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_convoca_assistant_import', array( __CLASS__, 'handle_import' ) );
	}

	/* ── Export ─────────────────────────────────── */

	/**
	 * Export knowledge base data as JSON download.
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-assistant' ) );
		}

		check_admin_referer( 'convoca_assistant_export' );

		$type = sanitize_text_field( wp_unslash( $_GET['type'] ?? 'knowledge' ) );

		switch ( $type ) {
			case 'knowledge':
				$data = self::export_knowledge();
				break;
			case 'settings':
				$data = self::export_settings();
				break;
			default:
				wp_die( esc_html__( 'Tipo de exportación no válido.', 'convoca-assistant' ) );
		}

		$filename = sprintf(
			'convoca-assistant-%s-%s.json',
			$type,
			gmdate( 'Y-m-d' )
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$json_data = wp_json_encode( $data );
		$length    = $json_data ? strlen( $json_data ) : 0;

		header( 'Content-Length: ' . $length );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json_data ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) : '';
		exit;
	}

	/* ── Import ─────────────────────────────────── */

	/**
	 * Import knowledge or settings from JSON upload.
	 *
	 * @return void
	 */
	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-assistant' ) );
		}

		check_admin_referer( 'convoca_assistant_import' );

		// Validate file upload.
		if ( empty( $_FILES['import_file'] ) ) {
			self::import_error( __( 'No se seleccionó ningún archivo.', 'convoca-assistant' ) );
		}

		$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			self::import_error( self::upload_error_message( $file['error'] ) );
		}

		// Validate size.
		if ( $file['size'] > self::MAX_IMPORT_SIZE ) {
			self::import_error(
				sprintf(
					/* translators: %s: max file size */
					__( 'El archivo excede el tamaño máximo de %s.', 'convoca-assistant' ),
					size_format( self::MAX_IMPORT_SIZE )
				)
			);
		}

		// Validate extension.
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			self::import_error( __( 'Solo se permiten archivos .json.', 'convoca-assistant' ) );
		}

		// Read and parse.
		$content = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content || empty( $content ) ) {
			self::import_error( __( 'El archivo está vacío o no se pudo leer.', 'convoca-assistant' ) );
		}

		$data = json_decode( $content, true );
		if ( null === $data || ! is_array( $data ) ) {
			self::import_error( __( 'El archivo no contiene JSON válido.', 'convoca-assistant' ) );
		}

		if ( empty( $data['_meta']['type'] ) ) {
			self::import_error( __( 'Formato de archivo no reconocido. Usa un archivo exportado por Convoca Assistant.', 'convoca-assistant' ) );
		}

		switch ( $data['_meta']['type'] ) {
			case 'knowledge':
				self::import_knowledge( $data );
				break;
			case 'settings':
				self::import_settings( $data );
				break;
			default:
				self::import_error( __( 'Tipo de importación no reconocido.', 'convoca-assistant' ) );
		}

		// Redirect back with success.
		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'convoca-assistant-tools', 'imported' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Show an import error and die.
	 *
	 * @param string $message Error message.
	 * @return never
	 */
	private static function import_error( string $message ): void {
		wp_die( esc_html( $message ) );
	}

	/**
	 * Translate upload error codes to messages.
	 *
	 * @param int $code PHP upload error code.
	 * @return string
	 */
	private static function upload_error_message( int $code ): string {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'El archivo excede el tamaño máximo permitido por el servidor.', 'convoca-assistant' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'El archivo excede el tamaño máximo del formulario.', 'convoca-assistant' ),
			UPLOAD_ERR_PARTIAL    => __( 'El archivo se subió parcialmente.', 'convoca-assistant' ),
			UPLOAD_ERR_NO_FILE    => __( 'No se seleccionó ningún archivo.', 'convoca-assistant' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Error del servidor: falta directorio temporal.', 'convoca-assistant' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Error del servidor: no se pudo escribir.', 'convoca-assistant' ),
		);

		return $messages[ $code ] ?? __( 'Error desconocido al subir el archivo.', 'convoca-assistant' );
	}

	/* ── Data exporters ─────────────────────────── */

	/**
	 * Export knowledge: FAQs, KB articles, synonyms, stop words.
	 *
	 * @return array
	 */
	private static function export_knowledge(): array {
		$data = array(
			'_meta'      => array(
				'type'      => 'knowledge',
				'version'   => CONVOCA_ASSISTANT_VERSION,
				'exported'  => gmdate( 'Y-m-d H:i:s' ),
				'locale'    => get_locale(),
				'site'      => get_bloginfo( 'url' ),
			),
			'synonyms'   => Synonyms::get_all(),
			'stop_words' => Synonyms::get_stop_words(),
			'faqs'       => array(),
			'kb'         => array(),
		);

		// Export FAQs.
		$faq_query = new \WP_Query(
			array(
				'post_type'      => 'convoca_faq',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $faq_query->posts as $post ) {
			$exclude = (bool) get_post_meta( $post->ID, '_convoca_assistant_exclude', true );
			$data['faqs'][] = array(
				'title'       => $post->post_title,
				'content'     => $post->post_content,
				'status'      => $post->post_status,
				'exclude'     => $exclude,
				'keywords'    => get_post_meta( $post->ID, '_convoca_assistant_keywords', true ),
				'weight'      => get_post_meta( $post->ID, '_convoca_assistant_weight', true ),
			);
		}

		// Export KB articles.
		$kb_query = new \WP_Query(
			array(
				'post_type'      => 'convoca_kb',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $kb_query->posts as $post ) {
			$exclude = (bool) get_post_meta( $post->ID, '_convoca_assistant_exclude', true );
			$data['kb'][] = array(
				'title'       => $post->post_title,
				'content'     => $post->post_content,
				'excerpt'     => $post->post_excerpt,
				'status'      => $post->post_status,
				'exclude'     => $exclude,
				'keywords'    => get_post_meta( $post->ID, '_convoca_assistant_keywords', true ),
				'weight'      => get_post_meta( $post->ID, '_convoca_assistant_weight', true ),
			);
		}

		return $data;
	}

	/**
	 * Export plugin settings.
	 *
	 * @return array
	 */
	private static function export_settings(): array {
		return array(
			'_meta'    => array(
				'type'      => 'settings',
				'version'   => CONVOCA_ASSISTANT_VERSION,
				'exported'  => gmdate( 'Y-m-d H:i:s' ),
				'site'      => get_bloginfo( 'url' ),
			),
			'settings' => get_option( 'convoca_assistant_settings', Installer::default_settings() ),
		);
	}

	/* ── Data importers ─────────────────────────── */

	/**
	 * Import knowledge from JSON data.
	 *
	 * @param array $data Structured import data.
	 * @return void
	 */
	private static function import_knowledge( array $data ): void {
		// Import synonyms.
		if ( ! empty( $data['synonyms'] ) && is_array( $data['synonyms'] ) ) {
			update_option( 'convoca_assistant_synonyms', $data['synonyms'] );
		}

		// Import stop words.
		if ( ! empty( $data['stop_words'] ) && is_array( $data['stop_words'] ) ) {
			Synonyms::set_stop_words( $data['stop_words'] );
		}

		// Import FAQs.
		if ( ! empty( $data['faqs'] ) && is_array( $data['faqs'] ) ) {
			foreach ( $data['faqs'] as $faq ) {
				if ( empty( $faq['title'] ) ) {
					continue;
				}
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'convoca_faq',
						'post_title'   => $faq['title'],
						'post_content' => $faq['content'] ?? '',
						'post_excerpt' => $faq['excerpt'] ?? '',
						'post_status'  => $faq['status'] ?? 'publish',
					)
				);

				if ( $post_id && ! is_wp_error( $post_id ) ) {
					if ( ! empty( $faq['keywords'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_keywords', $faq['keywords'] );
					}
					if ( ! empty( $faq['weight'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_weight', (float) $faq['weight'] );
					}
					if ( ! empty( $faq['exclude'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_exclude', true );
					}
				}
			}
		}

		// Import KB articles.
		if ( ! empty( $data['kb'] ) && is_array( $data['kb'] ) ) {
			foreach ( $data['kb'] as $article ) {
				if ( empty( $article['title'] ) ) {
					continue;
				}
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'convoca_kb',
						'post_title'   => $article['title'],
						'post_content' => $article['content'] ?? '',
						'post_excerpt' => $article['excerpt'] ?? '',
						'post_status'  => $article['status'] ?? 'publish',
					)
				);

				if ( $post_id && ! is_wp_error( $post_id ) ) {
					if ( ! empty( $article['keywords'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_keywords', $article['keywords'] );
					}
					if ( ! empty( $article['weight'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_weight', (float) $article['weight'] );
					}
					if ( ! empty( $article['exclude'] ) ) {
						update_post_meta( $post_id, '_convoca_assistant_exclude', true );
					}
				}
			}
		}

		Indexer::mark_dirty();
	}

	/**
	 * Import settings from JSON data.
	 *
	 * @param array $data Structured import data.
	 * @return void
	 */
	private static function import_settings( array $data ): void {
		if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$sanitized = Settings::sanitize( $data['settings'] );
			update_option( 'convoca_assistant_settings', $sanitized );
		}
	}
}
