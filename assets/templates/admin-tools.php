<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Herramientas', 'convoca-assistant' ); ?></h1>

	<?php if ( isset( $_GET['imported'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Importación completada.', 'convoca-assistant' ); ?></p></div>
	<?php endif; ?>

	<div class="convoca-admin-row">
		<div class="convoca-admin-col">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Exportar', 'convoca-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Exporta tu base de conocimiento o configuración como archivo JSON.', 'convoca-assistant' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="convoca_assistant_export" />
					<?php wp_nonce_field( 'convoca_assistant_export' ); ?>
					<select name="type">
						<option value="knowledge"><?php esc_html_e( 'Conocimiento (FAQ + KB + sinónimos)', 'convoca-assistant' ); ?></option>
						<option value="settings"><?php esc_html_e( 'Configuración', 'convoca-assistant' ); ?></option>
					</select>
					<?php submit_button( __( 'Exportar', 'convoca-assistant' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>

		<div class="convoca-admin-col">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Importar', 'convoca-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Importa un archivo JSON de conocimiento o configuración.', 'convoca-assistant' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="convoca_assistant_import" />
					<?php wp_nonce_field( 'convoca_assistant_import' ); ?>
					<input type="file" name="import_file" accept=".json" required />
					<?php submit_button( __( 'Importar', 'convoca-assistant' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</div>
	</div>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Mantenimiento', 'convoca-assistant' ); ?></h2>
		<button class="button" id="convoca-rebuild-index-tools">
			<?php esc_html_e( 'Regenerar índice', 'convoca-assistant' ); ?>
		</button>
		<button class="button" id="convoca-clear-logs">
			<?php esc_html_e( 'Limpiar logs antiguos', 'convoca-assistant' ); ?>
		</button>
		<span id="convoca-tools-msg" style="margin-left:10px;"></span>
	</div>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Debug', 'convoca-assistant' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'convoca_assistant' ); ?>
			<?php $settings = Convoca\Assistant\Settings::get_all(); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Modo debug', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[debug_mode]" value="1" <?php checked( $settings['debug_mode'] ); ?> /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Guardar', 'convoca-assistant' ), 'secondary', 'submit', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Probar búsqueda', 'convoca-assistant' ); ?></h3>
		<input type="text" id="convoca-debug-query" class="regular-text" placeholder="<?php esc_attr_e( 'Escribe una consulta de prueba…', 'convoca-assistant' ); ?>" />
		<button class="button" id="convoca-debug-search"><?php esc_html_e( 'Buscar', 'convoca-assistant' ); ?></button>
		<pre id="convoca-debug-result" style="background:#f0f0f1; padding:10px; margin-top:10px; max-height:400px; overflow:auto; display:none;"></pre>
	</div>
</div>
