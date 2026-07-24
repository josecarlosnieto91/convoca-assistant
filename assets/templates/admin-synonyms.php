<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Sinónimos', 'convoca-assistant' ); ?></h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sinónimos guardados correctamente.', 'convoca-assistant' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['removed'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Sinónimo eliminado.', 'convoca-assistant' ); ?></p></div>
	<?php endif; ?>

	<div class="convoca-admin-row">
		<div class="convoca-admin-col">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Añadir sinónimo', 'convoca-assistant' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="convoca_assistant_synonym_add" />
					<?php wp_nonce_field( 'convoca_assistant_synonym_add' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Término', 'convoca-assistant' ); ?></th>
							<td><input type="text" name="term" required class="regular-text" placeholder="ej: ordenador" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sinónimos', 'convoca-assistant' ); ?></th>
							<td>
								<textarea name="synonyms" rows="3" class="large-text" placeholder="computadora, pc, equipo (uno por línea)"></textarea>
								<p class="description"><?php esc_html_e( 'Un sinónimo por línea.', 'convoca-assistant' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Añadir sinónimo', 'convoca-assistant' ) ); ?>
				</form>
			</div>
		</div>

		<div class="convoca-admin-col">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Sinónimos existentes', 'convoca-assistant' ); ?></h2>
				<?php
				$synonyms = Convoca\Assistant\Synonyms::get_all();
				if ( empty( $synonyms ) ) : ?>
					<p><?php esc_html_e( 'No hay sinónimos definidos.', 'convoca-assistant' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Término', 'convoca-assistant' ); ?></th>
								<th><?php esc_html_e( 'Sinónimos', 'convoca-assistant' ); ?></th>
								<th><?php esc_html_e( 'Acción', 'convoca-assistant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $synonyms as $term => $syns ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $term ); ?></strong></td>
									<td><?php echo esc_html( implode( ', ', $syns ) ); ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<input type="hidden" name="action" value="convoca_assistant_synonym_remove" />
											<input type="hidden" name="term" value="<?php echo esc_attr( $term ); ?>" />
											<?php wp_nonce_field( 'convoca_assistant_synonym_remove' ); ?>
											<button class="button button-small button-link-delete"><?php esc_html_e( 'Eliminar', 'convoca-assistant' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Stop Words', 'convoca-assistant' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="convoca_assistant_stop_words_save" />
			<?php wp_nonce_field( 'convoca_assistant_stop_words_save' ); ?>
			<textarea name="stop_words" rows="6" class="large-text"><?php
				echo esc_textarea( implode( "\n", Convoca\Assistant\Synonyms::get_stop_words() ) );
			?></textarea>
			<p class="description"><?php esc_html_e( 'Una palabra por línea. Estas palabras se ignorarán en las búsquedas.', 'convoca-assistant' ); ?></p>
			<?php submit_button( __( 'Guardar stop words', 'convoca-assistant' ) ); ?>
		</form>
	</div>
</div>
