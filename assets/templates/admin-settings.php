<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
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
			<h2><?php esc_html_e( 'Fuentes y prioridad de respuesta', 'convoca-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Elige qué contenidos indexa el asistente y cuáles responden primero. Los tipos prioritarios (por defecto FAQ y Wiki) reciben un boost en el ranking; el resto solo responde cuando no hay un match claro.', 'convoca-assistant' ); ?></p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Fuentes activas', 'convoca-assistant' ); ?></th>
					<td>
						<label><input type="checkbox" name="convoca_assistant_settings[source_convoca_faq]" value="1" <?php checked( $settings['source_convoca_faq'] ); ?> /> <?php esc_html_e( 'FAQ', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[source_convoca_kb]" value="1" <?php checked( $settings['source_convoca_kb'] ); ?> /> <?php esc_html_e( 'Wiki / Base de conocimiento', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[source_page]" value="1" <?php checked( $settings['source_page'] ); ?> /> <?php esc_html_e( 'Páginas', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[source_post]" value="1" <?php checked( $settings['source_post'] ); ?> /> <?php esc_html_e( 'Entradas (blog)', 'convoca-assistant' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tipos prioritarios', 'convoca-assistant' ); ?></th>
					<td>
						<label><input type="checkbox" name="convoca_assistant_settings[priority_types][]" value="convoca_faq" <?php checked( in_array( 'convoca_faq', $settings['priority_types'], true ) ); ?> /> <?php esc_html_e( 'FAQ', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[priority_types][]" value="convoca_kb" <?php checked( in_array( 'convoca_kb', $settings['priority_types'], true ) ); ?> /> <?php esc_html_e( 'Wiki / Base de conocimiento', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[priority_types][]" value="page" <?php checked( in_array( 'page', $settings['priority_types'], true ) ); ?> /> <?php esc_html_e( 'Páginas', 'convoca-assistant' ); ?></label><br />
						<label><input type="checkbox" name="convoca_assistant_settings[priority_types][]" value="post" <?php checked( in_array( 'post', $settings['priority_types'], true ) ); ?> /> <?php esc_html_e( 'Entradas (blog)', 'convoca-assistant' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Boost de prioridad', 'convoca-assistant' ); ?></th>
					<td>
						<input type="number" name="convoca_assistant_settings[priority_boost]" value="<?php echo esc_attr( $settings['priority_boost'] ); ?>" step="0.05" min="1" max="3" />
						<span class="description"><?php esc_html_e( 'Multiplicador de score para los tipos prioritarios (1.35 por defecto).', 'convoca-assistant' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Peso FAQ', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_convoca_faq]" value="<?php echo esc_attr( $settings['weight_convoca_faq'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Peso Wiki', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_convoca_kb]" value="<?php echo esc_attr( $settings['weight_convoca_kb'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Peso páginas', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_page]" value="<?php echo esc_attr( $settings['weight_page'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Peso entradas (blog)', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_post]" value="<?php echo esc_attr( $settings['weight_post'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Longitud máxima de respuesta', 'convoca-assistant' ); ?></th>
					<td>
						<input type="number" name="convoca_assistant_settings[answer_max_length]" value="<?php echo esc_attr( $settings['answer_max_length'] ); ?>" min="100" max="2000" step="50" />
						<span class="description"><?php esc_html_e( 'Caracteres mostrados por respuesta en el chat (600 por defecto).', 'convoca-assistant' ); ?></span>
					</td>
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
