<?php
/**
 * Widget: floating chat widget and shortcode.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Manages the frontend chat widget: scripts, styles, and shortcode.
 */
class Widget {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_widget' ) );
		add_shortcode( 'convoca_assistant', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		if ( ! empty( $settings['maintenance_mode'] ) ) {
			return;
		}

		// Fuse.js bundled.
		wp_enqueue_script(
			'convoca-assistant-fuse',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/fuse.bundle.js',
			array(),
			'7.1.0',
			true
		);

		// Chat engine.
		wp_enqueue_script(
			'convoca-assistant-chat',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-chat.js',
			array( 'convoca-assistant-fuse' ),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		// Widget UI.
		wp_enqueue_script(
			'convoca-assistant-widget',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-widget.js',
			array( 'convoca-assistant-chat' ),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		// Styles.
		wp_enqueue_style(
			'convoca-assistant-widget',
			CONVOCA_ASSISTANT_ASSETS_URL . 'css/assistant-widget.css',
			array(),
			CONVOCA_ASSISTANT_VERSION
		);

		wp_enqueue_style(
			'convoca-assistant-chat',
			CONVOCA_ASSISTANT_ASSETS_URL . 'css/assistant-chat.css',
			array( 'convoca-assistant-widget' ),
			CONVOCA_ASSISTANT_VERSION
		);

		// Pass settings to JS.
		wp_localize_script(
			'convoca-assistant-chat',
			'convocaAssistant',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'convoca/v1/assistant/' ),
				'indexUrl'    => Indexer::get_index_url(),
				'indexExists' => Indexer::index_exists(),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'settings'    => array(
					'primaryColor' => $settings['widget_primary_color'] ?? '#2563eb',
					'title'        => $settings['widget_title'] ?? __( 'Asistente Virtual', 'convoca-assistant' ),
					'greeting'     => $settings['widget_greeting'] ?? __( '¡Hola! ¿En qué puedo ayudarte?', 'convoca-assistant' ),
					'threshold'    => (float) ( $settings['search_fuse_threshold'] ?? 0.4 ),
					'distance'     => (int) ( $settings['search_fuse_distance'] ?? 100 ),
					'maxResults'   => (int) ( $settings['search_max_results'] ?? 10 ),
					'weights'      => array(
						'title'    => 4,
						'keywords' => 3,
						'categories' => 2,
						'content'  => 1,
						'tags'     => 1,
					),
				),
				'i18n'        => array(
					'placeholder'    => __( 'Escribe tu pregunta aquí...', 'convoca-assistant' ),
					'send'           => __( 'Enviar', 'convoca-assistant' ),
					'typing'         => __( 'Escribiendo...', 'convoca-assistant' ),
					'loading'        => __( 'Preparando asistente...', 'convoca-assistant' ),
					'noResults'      => __( 'No encontré una respuesta. Reformula la pregunta o contacta con nosotros.', 'convoca-assistant' ),
					'viewSource'     => __( 'Ver fuente', 'convoca-assistant' ),
					'copy'           => __( 'Copiar', 'convoca-assistant' ),
					'copied'         => __( '¡Copiado!', 'convoca-assistant' ),
					'helpful'        => __( '¿Te ha servido?', 'convoca-assistant' ),
					'thanks'         => __( '¡Gracias por tu feedback!', 'convoca-assistant' ),
					'maintenance'    => $settings['maintenance_message'] ?? '',
				),
			)
		);
	}

	/**
	 * Render the floating widget button and chat container in footer.
	 *
	 * @return void
	 */
	public static function render_floating_widget(): void {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		if ( ! empty( $settings['maintenance_mode'] ) || empty( $settings['widget_enabled'] ) ) {
			return;
		}

		$position = $settings['widget_position'] ?? 'bottom-right';
		$color    = $settings['widget_primary_color'] ?? '#2563eb';
		$title    = esc_html( $settings['widget_title'] ?? __( 'Asistente Virtual', 'convoca-assistant' ) );

		include CONVOCA_ASSISTANT_DIR . 'assets/templates/widget-html.php';
	}

	/**
	 * Shortcode: [convoca_assistant]
	 * Embeds the chat inline instead of floating.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public static function shortcode( $atts, ?string $content = null ): string {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		if ( ! empty( $settings['maintenance_mode'] ) ) {
			return sprintf(
				'<p class="convoca-assistant-maintenance">%s</p>',
				esc_html( $settings['maintenance_message'] ?? '' )
			);
		}

		return '<div class="convoca-assistant-inline" role="dialog" aria-label="' .
			esc_attr__( 'Asistente Virtual', 'convoca-assistant' ) . '"></div>';
	}
}
