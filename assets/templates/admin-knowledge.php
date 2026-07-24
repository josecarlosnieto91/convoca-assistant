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
		<?php $providers = Convoca\Assistant\Provider_Registry::get_all(); ?>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Fuentes activas', 'convoca-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Marca las fuentes de conocimiento que quieres incluir en el asistente.', 'convoca-assistant' ); ?></p>
			<table class="form-table">
				<?php foreach ( $providers as $provider ) : ?>
					<tr>
						<th scope="row">
							<?php echo esc_html( $provider->get_name() ); ?>
							<?php if ( ! $provider->is_available() ) : ?>
								<span class="description">(<?php esc_html_e( 'no disponible', 'convoca-assistant' ); ?>)</span>
							<?php endif; ?>
						</th>
						<td>
							<input type="checkbox"
							       name="convoca_assistant_settings[<?php echo esc_attr( $provider->get_setting_key() ); ?>]"
							       value="1"
							       <?php checked( ! empty( $settings[ $provider->get_setting_key() ] ) ); ?>
							       <?php disabled( ! $provider->is_available() ); ?> />
							<span class="description"><?php echo esc_html( $provider->get_description() ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="convoca-admin-card">
			<h2><?php esc_html_e( 'Peso por tipo de contenido', 'convoca-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'A mayor peso, más relevante será ese tipo de contenido en las búsquedas.', 'convoca-assistant' ); ?></p>
			<table class="form-table">
				<?php foreach ( $providers as $provider ) : ?>
					<?php if ( ! $provider->is_available() ) { continue; } ?>
					<tr>
						<th scope="row"><?php echo esc_html( $provider->get_name() ); ?></th>
						<td>
							<input type="number"
							       name="convoca_assistant_settings[weight_<?php echo esc_attr( $provider->get_id() ); ?>]"
							       value="<?php echo esc_attr( $settings[ 'weight_' . $provider->get_id() ] ?? $provider->get_default_weight() ); ?>"
							       step="0.1" min="0" max="10" />
							<span class="description">(<?php esc_html_e( 'defecto:', 'convoca-assistant' ); ?> <?php echo esc_html( $provider->get_default_weight() ); ?>)</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<?php submit_button( __( 'Guardar cambios', 'convoca-assistant' ) ); ?>
	</form>
</div>
