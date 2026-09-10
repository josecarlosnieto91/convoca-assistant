/**
 * Convoca Assistant — Admin JS
 *
 * Dashboard analytics, index rebuild, debug search, clear logs,
 * and unanswered query management.
 *
 * @module ConvocaAssistant/Admin
 */

(function () {
	'use strict';

	const admin = window.convocaAssistant || {};
	if (!admin.restUrl) return;

	/* ── Dashboard ───────────────────────────────── */

	async function loadDashboardStats() {
		const el = id => document.getElementById(id);
		const totalEl = el('convoca-stat-total');
		if (!totalEl) return;

		try {
			const res = await fetch(admin.restUrl + 'stats?days=30', {
				headers: { 'X-WP-Nonce': admin.nonce },
			});
			const data = await res.json();

		const val = data.total || 0;
			const valEl = el('convoca-stat-total')?.querySelector('.convoca-stat-value');
			if (valEl) valEl.textContent = val;

			const resEl = el('convoca-stat-resolution')?.querySelector('.convoca-stat-value');
			if (resEl) resEl.textContent = (data.resolution_rate || 0) + '%';

			const unEl = el('convoca-stat-unanswered')?.querySelector('.convoca-stat-value');
			if (unEl) unEl.textContent = data.not_found || 0;

			const tEl = el('convoca-stat-time')?.querySelector('.convoca-stat-value');
			if (tEl) tEl.textContent = (data.avg_time_ms || 0) + 'ms';

			// Top queries (all user data rendered via textContent).
			const topList = el('convoca-top-queries');
			if (topList) {
				topList.innerHTML = '';
				if (!data.top_queries || data.top_queries.length === 0) {
					const li = document.createElement('li');
					li.style.color = '#999';
					li.textContent = 'Todavía no hay consultas registradas.';
					topList.appendChild(li);
				} else {
					data.top_queries.forEach(q => {
						const li = document.createElement('li');
						li.textContent = `${q.query} (${q.count}x, score: ${parseFloat(q.avg_score).toFixed(2)})`;
						topList.appendChild(li);
					});
				}
			}

			// Daily mini chart (values are counts; labels escaped via title attribute).
			const chartEl = el('convoca-chart-daily');
			if (chartEl) {
				if (!data.daily || data.daily.length === 0) {
					chartEl.innerHTML = '<p style="color:#999;padding:16px;">Sin datos todavía.</p>';
				} else {
					const max = Math.max(...data.daily.map(d => d.count), 1);
					chartEl.innerHTML =
						'<div style="display:flex;align-items:flex-end;gap:2px;height:180px;padding:10px;">' +
						data.daily.map(d =>
							`<div title="${escapeHtml(d.day)}: ${parseInt(d.count, 10)}" style="flex:1;background:#2563eb;height:${(d.count/max)*100}%;min-height:2px;border-radius:2px 2px 0 0;"></div>`
						).join('') + '</div>';
				}
			}
		} catch (err) {
			console.error('[Convoca Admin] Stats error:', err);
		}
	}

	/* ── Index rebuild ────────────────────────────── */

	async function rebuildIndex() {
		const btn = document.getElementById('convoca-rebuild-index') ||
		            document.getElementById('convoca-rebuild-index-tools');
		const msg = document.getElementById('convoca-rebuild-msg') ||
		            document.getElementById('convoca-tools-msg');
		if (!btn) return;

		btn.disabled = true;
		btn.textContent = '⏳ Regenerando…';
		if (msg) msg.textContent = '';

		try {
			const res = await fetch(admin.restUrl + 'rebuild-index', {
				method: 'POST', headers: { 'X-WP-Nonce': admin.nonce },
			});
			const data = await res.json();

			if (data.success && msg) {
				const size = data.size ? Math.round(data.size / 1024) + ' KB' : '';
				msg.textContent = `✅ ${data.count} entradas${size ? ' (' + size + ')' : ''}`;
				msg.style.color = '#46b450';
			} else if (msg) {
				msg.textContent = '❌ ' + (data.error || 'Error desconocido');
				msg.style.color = '#dc3232';
			}
		} catch (err) {
			if (msg) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#dc3232'; }
		} finally {
			btn.disabled = false;
			btn.textContent = '🔄 Regenerar índice ahora';
		}
	}

	/* ── Clear logs ───────────────────────────────── */

	async function clearLogs() {
		const btn = document.getElementById('convoca-clear-logs');
		const msg = document.getElementById('convoca-tools-msg');
		if (!btn) return;

		if (!confirm('¿Eliminar todos los logs de interacción? Esta acción no se puede deshacer.')) return;

		btn.disabled = true;
		btn.textContent = '⏳ Limpiando…';

		try {
			const res = await fetch(admin.restUrl + 'clear-logs', {
				method: 'POST', headers: { 'X-WP-Nonce': admin.nonce },
			});
			const data = await res.json();

			if (msg) {
				msg.textContent = data.success ? '✅ Logs eliminados' : '❌ Error';
				msg.style.color = data.success ? '#46b450' : '#dc3232';
			}
		} catch (err) {
			if (msg) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#dc3232'; }
		} finally {
			btn.disabled = false;
			btn.textContent = '🧹 Limpiar logs antiguos';
		}
	}

	/* ── Unanswered queries ───────────────────────── */

	async function loadUnanswered() {
		const tbody = document.getElementById('convoca-unanswered-rows');
		if (!tbody) return;

		try {
			const res = await fetch(admin.restUrl + 'unanswered?limit=50', {
				headers: { 'X-WP-Nonce': admin.nonce },
			});
			const data = await res.json();

			if (data.length === 0) {
				tbody.innerHTML = '<tr><td colspan="4">No hay consultas sin respuesta. 🎉</td></tr>';
				return;
			}

			tbody.innerHTML = data.map(q =>
				`<tr>
					<td>${escapeHtml(q.query)}</td>
					<td>${q.count}</td>
					<td>${q.last_seen || '—'}</td>
					<td><a class="button button-small" href="${admin.createFaqUrl + '&post_title=' + encodeURIComponent(q.query)}" target="_blank">➕ Crear FAQ</a></td>
				</tr>`
			).join('');
		} catch (err) {
			console.error('[Convoca Admin] Unanswered error:', err);
		}
	}

	/* ── Analytics table ──────────────────────────── */

	async function loadAnalytics() {
		const summary = document.getElementById('convoca-analytics-summary');
		const tbody   = document.getElementById('convoca-analytics-rows');
		if (!summary && !tbody) return;

		const notice = el => {
			if (el) el.innerHTML = '<table class="convoca-status-table"><tr><td>' +
				escapeHtml('Se encontró un error al cargar los datos.') + '</td></tr></table>';
		};

		try {
			if (summary) {
				const res  = await fetch(admin.restUrl + 'stats?days=7', { headers: { 'WP-Nonce': admin.nonce, 'X-WP-Nonce': admin.nonce } });
				const data = await res.json();
				summary.innerHTML =
					'<table class="convoca-status-table">' +
					`<tr><td>Consultas (7 días):</td><td><strong>${data.total || 0}</strong></td></tr>` +
					`<tr><td>Tasa de resolución:</td><td><strong>${data.resolution_rate || 0}%</strong></td></tr>` +
					`<tr><td>Sin respuesta:</td><td><strong>${data.not_found || 0}</strong></td></tr>` +
					`<tr><td>Tiempo medio:</td><td><strong>${data.avg_time_ms || 0} ms</strong></td></tr>` +
					'</table>';
			}

			if (tbody) {
				const res  = await fetch(admin.restUrl + 'recent?limit=20', { headers: { 'X-WP-Nonce': admin.nonce } });
				const rows = await res.json();

				if (!Array.isArray(rows) || rows.length === 0) {
					tbody.innerHTML = '<tr><td colspan="6">' +
						escapeHtml('Todavía no hay consultas registradas.') + '</td></tr>';
					return;
				}

				tbody.innerHTML = rows.map(r =>
					`<tr>
						<td>${escapeHtml(r.query || '')}</td>
						<td>${(parseFloat(r.score) || 0).toFixed(2)}</td>
						<td>${r.source ? escapeHtml(r.source) : (parseInt(r.response_found, 10) ? '—' : escapeHtml('Sin resultado'))}</td>
						<td>${parseInt(r.clicked, 10) ? '✔' : '—'}</td>
						<td>${parseInt(r.time_ms, 10) || 0} ms</td>
						<td>${escapeHtml(r.created_at || '')}</td>
					</tr>`
				).join('');
			}
		} catch (err) {
			console.error('[Convoca Admin] Analytics error:', err);
			notice(tbody || summary);
		}
	}

	/* ── Debug search ─────────────────────────────── */

	function initDebugSearch() {
		const input = document.getElementById('convoca-debug-query');
		const result = document.getElementById('convoca-debug-result');
		const btn = document.getElementById('convoca-debug-search');
		if (!input || !result || !btn) return;

		btn.addEventListener('click', async () => {
			const query = input.value.trim();
			if (!query) return;

			result.style.display = 'block';
			result.textContent = '🔍 Buscando…';

			try {
				const res = await fetch(admin.restUrl + 'search', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': admin.nonce },
					body: JSON.stringify({ query }),
				});
				const data = await res.json();
				result.textContent = JSON.stringify(data, null, 2);
			} catch (err) {
				result.textContent = '❌ Error: ' + err.message;
			}
		});

		// Enter key
		input.addEventListener('keydown', e => {
			if (e.key === 'Enter') btn.click();
		});
	}

	/* ── Helpers ──────────────────────────────────── */

	function escapeHtml(text) {
		const d = document.createElement('div');
		d.textContent = text;
		return d.innerHTML;
	}

	/* ── Init ──────────────────────────────────────── */

	function init() {
		loadDashboardStats();
		loadUnanswered();
		loadAnalytics();
		initDebugSearch();

		const rebuildBtn = document.getElementById('convoca-rebuild-index') ||
		                   document.getElementById('convoca-rebuild-index-tools');
		if (rebuildBtn) rebuildBtn.addEventListener('click', rebuildIndex);

		const clearBtn = document.getElementById('convoca-clear-logs');
		if (clearBtn) clearBtn.addEventListener('click', clearLogs);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
