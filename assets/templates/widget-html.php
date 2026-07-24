<div id="convoca-assistant-widget" class="convoca-assistant-widget"
     data-position="<?php echo esc_attr( $position ); ?>"
     style="--convoca-primary: <?php echo esc_attr( $color ); ?>;">

	<!-- Floating button -->
	<button id="convoca-assistant-toggle" class="convoca-assistant-toggle"
	        aria-label="<?php esc_attr_e( 'Abrir asistente virtual', 'convoca-assistant' ); ?>"
	        aria-expanded="false"
	        aria-controls="convosa-chat-container">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
			<line x1="12" y1="8" x2="12" y2="12"/>
			<line x1="9" y1="11" x2="15" y2="11"/>
		</svg>
	</button>

	<!-- Chat window -->
	<div id="convosa-chat-container" class="convoca-chat-container" role="dialog"
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
			        aria-label="<?php esc_attr_e( 'Enviar', 'convoca-assistant' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:auto;">
					<path d="M2 21L23 12 2 3v6l15 3-15 3v6z"/>
				</svg>
			</button>
		</div>
	</div>
</div>
