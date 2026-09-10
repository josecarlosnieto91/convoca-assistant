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
		add_shortcode( 'convoca_assistant', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$settings = Settings::get_all();

		if ( ! empty( $settings['maintenance_mode'] ) ) {
			return;
		}

		// Motor del chat: Fuse + memoria de sesión + engine.
		//
		// Se REGISTRAN pero NO se encolan. Pesan ~21 KiB comprimidos y solo hacen
		// falta cuando alguien abre el chat: assistant-widget.js los inyecta en el
		// primer open() (URLs en convocaAssistant.lazyAssets). Encender los tres en
		// cada visita era peso muerto para quien nunca abre el widget.
		wp_register_script(
			'convoca-assistant-fuse',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/fuse.bundle.js',
			array(),
			'7.1.0',
			true
		);

		wp_register_script(
			'convoca-assistant-session',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-session.js',
			array(),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		wp_register_script(
			'convoca-assistant-chat',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-chat.js',
			array( 'convoca-assistant-fuse', 'convoca-assistant-session' ),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		// Widget UI: sí se encola. Es el botón (y quien construye el DOM si el tema
		// no imprime el pie), así que debe estar desde el principio.
		wp_enqueue_script(
			'convoca-assistant-widget',
			CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-widget.js',
			array(),
			CONVOCA_ASSISTANT_VERSION,
			true
		);

		// Estilos: el del botón siempre; el del chat solo al abrirlo.
		wp_enqueue_style(
			'convoca-assistant-widget',
			CONVOCA_ASSISTANT_ASSETS_URL . 'css/assistant-widget.css',
			array(),
			CONVOCA_ASSISTANT_VERSION
		);

		wp_register_style(
			'convoca-assistant-chat',
			CONVOCA_ASSISTANT_ASSETS_URL . 'css/assistant-chat.css',
			array( 'convoca-assistant-widget' ),
			CONVOCA_ASSISTANT_VERSION
		);

		// Con el chat embebido ([convoca_assistant]) el diálogo se ve sin interacción,
		// así que ahí sí se encola todo: no tiene sentido diferir.
		$inline_chat = self::page_has_inline_chat();

		if ( $inline_chat ) {
			wp_enqueue_script( 'convoca-assistant-chat' );
			wp_enqueue_style( 'convoca-assistant-chat' );
		}

		$lazy_assets = $inline_chat ? null : array(
			'css' => array(
				add_query_arg( 'ver', CONVOCA_ASSISTANT_VERSION, CONVOCA_ASSISTANT_ASSETS_URL . 'css/assistant-chat.css' ),
			),
			'js'  => array(
				add_query_arg( 'ver', '7.1.0', CONVOCA_ASSISTANT_ASSETS_URL . 'js/fuse.bundle.js' ),
				add_query_arg( 'ver', CONVOCA_ASSISTANT_VERSION, CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-session.js' ),
				add_query_arg( 'ver', CONVOCA_ASSISTANT_VERSION, CONVOCA_ASSISTANT_ASSETS_URL . 'js/assistant-chat.js' ),
			),
		);

		// Pass settings to JS.
		wp_localize_script(
			'convoca-assistant-widget',
			'convocaAssistant',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'convoca/v1/assistant/' ),
				'indexUrl'    => Indexer::get_index_url(),
				'indexExists' => Indexer::index_exists(),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'settings'    => array(
					'primaryColor'    => $settings['widget_primary_color'] ?? '#2563eb',
					'title'           => $settings['widget_title'] ?? __( 'Asistente Virtual', 'convoca-assistant' ),
					'greeting'        => $settings['widget_greeting'] ?? __( '¡Hola! ¿En qué puedo ayudarte?', 'convoca-assistant' ),
					'threshold'       => (float) ( $settings['search_fuse_threshold'] ?? 0.4 ),
					'distance'        => (int) ( $settings['search_fuse_distance'] ?? 100 ),
					'maxResults'      => (int) ( $settings['search_max_results'] ?? 10 ),
					'maxAnswerLength' => (int) ( $settings['answer_max_length'] ?? 600 ),
					'priorityTypes'   => ! empty( $settings['priority_types'] ) ? (array) $settings['priority_types'] : array( 'convoca_faq', 'convoca_kb' ),
					'priorityBoost'   => (float) ( $settings['priority_boost'] ?? 1.0 ),
					'directThreshold' => (float) ( $settings['direct_threshold'] ?? 0.55 ),
					'rankingWeights'  => array(
						'fuzzy'      => (float) ( $settings['weights_fuzzy'] ?? 0.45 ),
						'graph'      => (float) ( $settings['weights_graph'] ?? 0.10 ),
						'exact'      => (float) ( $settings['weights_exact'] ?? 0.15 ),
						'exactTitle' => (float) ( $settings['weights_exact_title'] ?? 0.15 ),
					),
					'weights'         => array(
						'title'      => 4,
						'keywords'   => 3,
						'categories' => 2,
						'content'    => 1,
						'tags'       => 1,
					),
					'contact'         => array(
						'email'    => $settings['contact_email'] ?? '',
						'phone'    => $settings['contact_phone'] ?? '',
						'whatsapp' => $settings['contact_whatsapp'] ?? '',
					),
				),
				'i18n'        => array(
					'placeholder' => __( 'Escribe tu pregunta aquí...', 'convoca-assistant' ),
					'send'        => __( 'Enviar', 'convoca-assistant' ),
					'typing'      => __( 'Escribiendo...', 'convoca-assistant' ),
					'loading'     => __( 'Preparando asistente...', 'convoca-assistant' ),
					'noResults'   => __( 'No encontré una respuesta. Reformula la pregunta o contacta con nosotros.', 'convoca-assistant' ),
					'talkLabel'   => __( '¿Hablamos?', 'convoca-assistant' ),
					'viewSource'  => __( 'Ver fuente', 'convoca-assistant' ),
					'copy'        => __( 'Copiar', 'convoca-assistant' ),
					'copied'      => __( '¡Copiado!', 'convoca-assistant' ),
					'helpful'     => __( '¿Te ha servido?', 'convoca-assistant' ),
					'thanks'      => __( '¡Gracias por tu feedback!', 'convoca-assistant' ),
					'maintenance' => ! empty( $settings['maintenance_mode'] ) ? ( $settings['maintenance_message'] ?? '' ) : '',
				),
				'lazyAssets'  => $lazy_assets,
			)
		);
	}

	/**
	 * Whether the current page embeds the chat inline via [convoca_assistant].
	 *
	 * En esa página el diálogo se ve sin interacción: se encola todo de entrada.
	 *
	 * @return bool
	 */
	private static function page_has_inline_chat(): bool {
		$post = get_post();

		return (bool) ( $post && ! empty( $post->post_content ) && has_shortcode( $post->post_content, 'convoca_assistant' ) );
	}

	/**
	 * Render the floating widget button and chat container in footer.
	 *
	 * @return void
	 */
	public static function render_floating_widget(): void {
		$settings = Settings::get_all();

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
		$settings = Settings::get_all();

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
