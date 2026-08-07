<div id="convoca-assistant-widget" class="convoca-assistant-widget"
     data-position="<?php echo esc_attr( $position ); ?>"
     style="--convoca-primary: <?php echo esc_attr( $color ); ?>;">

	<!-- Floating button -->
	<button id="convoca-assistant-toggle" class="convoca-assistant-toggle"
	        aria-label="<?php esc_attr_e( 'Abrir asistente virtual', 'convoca-assistant' ); ?>"
	        aria-expanded="false"
	        aria-controls="convoca-chat-container">
		<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
			<path d="M8 10h8M8 14h5"/>
		</svg>
	</button>

	<!-- Chat window -->
	<div id="convoca-chat-container" class="convoca-chat-container" role="dialog"
	     aria-label="<?php echo esc_attr( $title ); ?>"
	     aria-hidden="true"
	     aria-modal="true">

		<!-- Header -->
		<div class="convoca-chat-header">
			<span class="convoca-chat-title"><?php echo esc_html( $title ); ?></span>
			<button class="convoca-chat-close" aria-label="<?php esc_attr_e( 'Cerrar', 'convoca-assistant' ); ?>">&times;</button>
		</div>

		<!-- Messages -->
		<div class="convoca-chat-messages" role="log" aria-live="polite" aria-relevant="additions" aria-atomic="false">
			<div class="convoca-message convoca-message-bot">
				<?php echo esc_html( $settings['widget_greeting'] ?? __( '¡Hola! ¿En qué puedo ayudarte?', 'convoca-assistant' ) ); ?>
			</div>
		</div>

		<!-- Suggestions -->
		<div class="convoca-chat-suggestions"></div>

		<!-- Input -->
		<div class="convoca-chat-input-area">
			<input type="text" id="convoca-chat-input"
			       class="convoca-chat-input"
			       placeholder="<?php esc_attr_e( 'Escribe tu pregunta aquí...', 'convoca-assistant' ); ?>"
			       aria-label="<?php esc_attr_e( 'Pregunta', 'convoca-assistant' ); ?>"
			       autocomplete="off" />
			<button id="convoca-chat-send" class="convoca-chat-send"
			        aria-label="<?php esc_attr_e( 'Enviar', 'convoca-assistant' ); ?>">&#9654;</button>
		</div>
	</div>
</div>
