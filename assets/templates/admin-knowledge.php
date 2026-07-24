<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Conocimiento', 'convoca-assistant' ); ?></h1>
	<p><?php esc_html_e( 'Gestiona las fuentes de conocimiento del asistente. Puedes activar o desactivar tipos de contenido y ajustar su peso en las búsquedas.', 'convoca-assistant' ); ?></p>

	<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ajustes guardados.', 'convoca-assistant' ); ?></p></div>
	<?php endif; ?>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Contenido indexable', 'convoca-assistant' ); ?></h2>
		<p><?php esc_html_e( 'Accede directamente a los listados para gestionar el contenido:', 'convoca-assistant' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=convoca_faq' ) ); ?>" class="button"><?php esc_html_e( 'Gestionar FAQs', 'convoca-assistant' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=convoca_kb' ) ); ?>" class="button"><?php esc_html_e( 'Gestionar Base de Conocimiento', 'convoca-assistant' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="button"><?php esc_html_e( 'Entradas', 'convoca-assistant' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button"><?php esc_html_e( 'Páginas', 'convoca-assistant' ); ?></a>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'convoca_assistant' ); ?>
		<?php $settings = Convoca\Assistant\Settings::get_all(); ?>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Fuentes activas', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Entradas', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[source_post]" value="1" <?php checked( $settings['source_post'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Páginas', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[source_page]" value="1" <?php checked( $settings['source_page'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'FAQ (CPT)', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[source_convoca_faq]" value="1" <?php checked( $settings['source_convoca_faq'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Base de Conocimiento (CPT)', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[source_convoca_kb]" value="1" <?php checked( $settings['source_convoca_kb'] ); ?> /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Productos WooCommerce', 'convoca-assistant' ); ?></th>
					<td><input type="checkbox" name="convoca_assistant_settings[source_woocommerce]" value="1" <?php checked( $settings['source_woocommerce'] ); ?> <?php disabled( ! class_exists( 'WooCommerce' ) ); ?> /></td>
				</tr>
			</table>
		</div>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Peso por tipo de contenido', 'convoca-assistant' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'FAQ', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_convoca_faq]" value="<?php echo esc_attr( $settings['weight_convoca_faq'] ); ?>" step="0.1" min="0" max="10" /> <span class="description">(0-10)</span></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Base de Conocimiento', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_convoca_kb]" value="<?php echo esc_attr( $settings['weight_convoca_kb'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Entradas', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_post]" value="<?php echo esc_attr( $settings['weight_post'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Páginas', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_page]" value="<?php echo esc_attr( $settings['weight_page'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Productos', 'convoca-assistant' ); ?></th>
					<td><input type="number" name="convoca_assistant_settings[weight_product]" value="<?php echo esc_attr( $settings['weight_product'] ); ?>" step="0.1" min="0" max="10" /></td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Guardar cambios', 'convoca-assistant' ) ); ?>
	</form>
</div>
