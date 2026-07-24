<div class="wrap convoca-assistant-admin">
	<h1><?php esc_html_e( 'Convoca Assistant — Dashboard', 'convoca-assistant' ); ?></h1>

	<div class="convoca-stats-grid">
		<div class="convoca-stat-card" id="convoca-stat-total">
			<span class="convoca-stat-value">—</span>
			<span class="convoca-stat-label"><?php esc_html_e( 'Consultas (30d)', 'convoca-assistant' ); ?></span>
		</div>
		<div class="convoca-stat-card" id="convoca-stat-resolution">
			<span class="convoca-stat-value">—%</span>
			<span class="convoca-stat-label"><?php esc_html_e( 'Tasa de resolución', 'convoca-assistant' ); ?></span>
		</div>
		<div class="convoca-stat-card" id="convoca-stat-unanswered">
			<span class="convoca-stat-value">—</span>
			<span class="convoca-stat-label"><?php esc_html_e( 'Sin respuesta (30d)', 'convoca-assistant' ); ?></span>
		</div>
		<div class="convoca-stat-card" id="convoca-stat-time">
			<span class="convoca-stat-value">—</span>
			<span class="convoca-stat-label"><?php esc_html_e( 'Tiempo medio', 'convoca-assistant' ); ?></span>
		</div>
	</div>

	<div class="convoca-admin-row" style="display:flex;gap:20px;flex-wrap:wrap;">
		<div class="convoca-admin-col" style="flex:2;min-width:300px;">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Consultas por día', 'convoca-assistant' ); ?></h2>
				<div id="convoca-chart-daily" style="height:200px;background:#f0f0f1;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#888;">
					<?php esc_html_e( 'Cargando datos…', 'convoca-assistant' ); ?>
				</div>
			</div>
		</div>
		<div class="convoca-admin-col" style="flex:1;min-width:280px;">
			<div class="convoca-admin-card">
				<h2><?php esc_html_e( 'Top consultas', 'convoca-assistant' ); ?></h2>
				<ol id="convoca-top-queries" style="margin:0;">
					<li style="color:#999;"><?php esc_html_e( 'Cargando…', 'convoca-assistant' ); ?></li>
				</ol>
			</div>
		</div>
	</div>

	<div class="convoca-admin-card">
		<h2><?php esc_html_e( 'Estado del índice', 'convoca-assistant' ); ?></h2>
		<table class="convoca-status-table">
			<tr>
				<td><?php esc_html_e( 'Estado:', 'convoca-assistant' ); ?></td>
				<td id="convoca-index-status">
					<?php if ( \Convoca\Assistant\Indexer::index_exists() ) : ?>
						<span style="color:#46b450;">● <?php esc_html_e( 'Generado', 'convoca-assistant' ); ?></span>
						<?php if ( \Convoca\Assistant\Indexer::is_dirty() ) : ?>
							<span style="color:#f0ad4e;margin-left:8px;"><?php esc_html_e( '(pendiente de regenerar)', 'convoca-assistant' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#dc3232;">● <?php esc_html_e( 'No generado', 'convoca-assistant' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Entradas:', 'convoca-assistant' ); ?></td>
				<td><?php echo esc_html( \Convoca\Assistant\Indexer::get_stats()['total'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Tamaño:', 'convoca-assistant' ); ?></td>
				<td><?php echo esc_html( \Convoca\Assistant\Indexer::get_index_size() ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Última generación:', 'convoca-assistant' ); ?></td>
				<td><?php echo esc_html( \Convoca\Assistant\Indexer::get_stats()['generated'] ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Hash:', 'convoca-assistant' ); ?></td>
				<td><code><?php echo esc_html( \Convoca\Assistant\Indexer::get_stats()['hash'] ); ?></code></td>
			</tr>
		</table>
		<button class="button button-primary" id="convoca-rebuild-index">
			<?php esc_html_e( 'Regenerar índice ahora', 'convoca-assistant' ); ?>
		</button>
		<span id="convoca-rebuild-msg" style="margin-left:10px;"></span>
	</div>
</div>
