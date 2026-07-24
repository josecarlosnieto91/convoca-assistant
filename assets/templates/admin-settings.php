<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Ajustes', 'convoca-assistant' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'convoca_assistant' ); ?>
		<?php $settings = Convoca\Assistant\Settings::get_all(); ?>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Widget', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Habilitar widget flotante', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[widget_enabled]" value="1" <?php checked( $settings['widget_enabled'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Posición', 'convoca-assistant' ); ?></th>
					<td>
						<select name="convoca_assistant_settings[widget_position]">
							<option value="bottom-right" <?php selected( $settings['widget_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Abajo derecha', 'convoca-assistant' ); ?></option>
							<option value="bottom-left" <?php selected( $settings['widget_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Abajo izquierda', 'convoca-assistant' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Color primario', 'convoca-assistant' ); ?></th>
					<td><input type="text" name="convoca_assistant_settings[widget_primary_color]" value="<?php echo esc_attr( $settings['widget_primary_color'] ); ?>" class="convoca-color-picker" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Título', 'convoca-assistant' ); ?></th>
					<td><input type="text" name="convoca_assistant_settings[widget_title]" value="<?php echo esc_attr( $settings['widget_title'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mensaje de bienvenida', 'convoca-assistant' ); ?></th>
					<td><textarea name="convoca_assistant_settings[widget_greeting]" rows="2" class="large-text"><?php echo esc_textarea( $settings['widget_greeting'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-apertura', 'convoca-assistant' ); ?></th>
					<td>
						<select name="convoca_assistant_settings[widget_auto_open]">
							<option value="never" <?php selected( $settings['widget_auto_open'], 'never' ); ?>><?php esc_html_e( 'Nunca', 'convoca-assistant' ); ?></option>
							<option value="always" <?php selected( $settings['widget_auto_open'], 'always' ); ?>><?php esc_html_e( 'Siempre', 'convoca-assistant' ); ?></option>
							<option value="scroll" <?php selected( $settings['widget_auto_open'], 'scroll' ); ?>><?php esc_html_e( 'Al hacer scroll', 'convoca-assistant' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Búsqueda', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Modo de búsqueda', 'convoca-assistant' ); ?></th>
					<td>
						<select name="convoca_assistant_settings[search_mode]">
							<option value="client" <?php selected( $settings['search_mode'], 'client' ); ?>><?php esc_html_e( 'Cliente (Fuse.js)', 'convoca-assistant' ); ?></option>
							<option value="server" <?php selected( $settings['search_mode'], 'server' ); ?>><?php esc_html_e( 'Servidor', 'convoca-assistant' ); ?></option>
							<option value="both" <?php selected( $settings['search_mode'], 'both' ); ?>><?php esc_html_e( 'Ambos', 'convoca-assistant' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Fallback servidor', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[search_fallback]" value="1" <?php checked( $settings['search_fallback'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Máx. resultados', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[search_max_results]" value="<?php echo esc_attr( $settings['search_max_results'] ); ?>" min="1" max="50" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Umbral Fuse.js', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[search_fuse_threshold]" value="<?php echo esc_attr( $settings['search_fuse_threshold'] ); ?>" step="0.05" min="0" max="1" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Distancia Fuse.js', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[search_fuse_distance]" value="<?php echo esc_attr( $settings['search_fuse_distance'] ); ?>" min="0" max="500" /></td>
				</tr>
			</table>
		</div>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Privacidad', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Registrar interacciones', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[log_enabled]" value="1" <?php checked( $settings['log_enabled'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Anonimizar IP/UA', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[log_anonymous]" value="1" <?php checked( $settings['log_anonymous'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Retención (días)', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[log_retention_days]" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" min="1" max="365" /></td>
				</tr>
			</table>
		</div>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Mantenimiento', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Modo mantenimiento', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[maintenance_mode]" value="1" <?php checked( $settings['maintenance_mode'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Mensaje', 'convoca-assistant' ); ?></th>
					<td><input type="text" name="convoca_assistant_settings[maintenance_message]" value="<?php echo esc_attr( $settings['maintenance_message'] ); ?>" class="regular-text" /></td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Guardar ajustes', 'convoca-assistant' ) ); ?>
	</form>
</div>
