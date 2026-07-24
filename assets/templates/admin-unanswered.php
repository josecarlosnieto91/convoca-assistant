<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Consultas sin respuesta', 'convoca-assistant' ); ?></h1>
	<p><?php esc_html_e( 'Estas consultas de usuarios no encontraron contenido relevante. Añádelas a tu base de conocimiento para mejorar el asistente.', 'convoca-assistant' ); ?></p>

	<div class="convoca-admin-card">
		<table class="wp-list-table widefat fixed striped" id="convoca-unanswered-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Consulta', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Veces', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Última vez', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Acción', 'convoca-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody id="convoca-unanswered-rows">
				<tr><td colspan="4"><?php esc_html_e( 'Cargando…', 'convoca-assistant' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
