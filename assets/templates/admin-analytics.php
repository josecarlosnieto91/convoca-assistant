<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Analytics', 'convoca-assistant' ); ?></h1>
	<p><?php esc_html_e( 'Estadísticas de uso del asistente virtual.', 'convoca-assistant' ); ?></p>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Resumen', 'convoca-assistant' ); ?></h2>
		<div id="convoca-analytics-summary">
			<p class="description"><?php esc_html_e( 'Cargando datos…', 'convoca-assistant' ); ?></p>
		</div>
	</div>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Últimas consultas', 'convoca-assistant' ); ?></h2>
		<table class="wp-list-table widefat fixed striped" id="convoca-analytics-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Consulta', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Score', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Fuente', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Click', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Tiempo', 'convoca-assistant' ); ?></th>
					<th><?php esc_html_e( 'Fecha', 'convoca-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody id="convoca-analytics-rows">
				<tr><td colspan="6"><?php esc_html_e( 'Cargando…', 'convoca-assistant' ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
