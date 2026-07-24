/**
 * Convoca Assistant — Widget UI
 *
 * Floating chat widget with full keyboard support, accessibility,
 * suggestion chips, markdown rendering, smart scroll, and feedback.
 *
 * @module ConvocaAssistant/Widget
 */

(function () {
	'use strict';

	class ConvocaWidget {
		constructor() {
			this.chat = null;
			this.isOpen = false;
			this.config = window.convocaAssistant || {};
			this.dom = {};
			this.userScrolledUp = false;
			this.messageCount = 0;
			this.lastQuery = '';
		}

		/**
		 * Initialize the widget.
		 */
		async init() {
			this.dom = {
				widget:      document.getElementById('convoca-assistant-widget'),
				toggle:      document.getElementById('convoca-assistant-toggle'),
				container:   document.getElementById('convosa-chat-container'),
				close:       document.querySelector('.convoca-chat-close'),
				messages:    document.querySelector('.convoca-chat-messages'),
				input:       document.getElementById('convoca-chat-input'),
				send:        document.getElementById('convoca-chat-send'),
				suggestions: document.querySelector('.convoca-chat-suggestions'),
				status:      document.querySelector('.convoca-chat-status'),
			};

			// Initialize session memory
			this.session = new window.ConvocaSession();

			if (!this.dom.widget) return;

			// Check maintenance mode
			if (this.config.i18n?.maintenance) {
				this.showMaintenance();
				return;
			}

			// Initialize chat engine
			this.chat = new window.ConvocaChat();
			this.showStatus(this.config.i18n?.loading || 'Preparando asistente…');

			const loaded = await this.chat.init();
			this.hideStatus();

			if (!loaded) {
				this.showError('No se pudo cargar la base de conocimiento.');
				return;
			}

			// Bind events
			this.dom.toggle.addEventListener('click', () => this.toggle());
			this.dom.close.addEventListener('click', () => this.close());
			this.dom.send.addEventListener('click', () => this.send());
			this.dom.input.addEventListener('keydown', (e) => this.onInputKey(e));
			this.dom.messages.addEventListener('scroll', () => this.onScroll(), { passive: true });
			document.addEventListener('keydown', (e) => this.onGlobalKey(e));

			// Observe auto-open setting
			const autoOpen = this.config.settings?.autoOpen || 'never';
			if (autoOpen === 'always') {
				setTimeout(() => this.open(), 1000);
			} else if (autoOpen === 'scroll') {
				this.initScrollTrigger();
			}

			// Idle pulse
			this.startIdlePulse();
		}

		/* ── Open / Close / Toggle ──────────────── */

		open() {
			if (this.isOpen) return;
			this.isOpen = true;
			this.dom.container.classList.add('convoca-open');
			this.dom.container.setAttribute('aria-hidden', 'false');
			this.dom.toggle.setAttribute('aria-label', 'Cerrar asistente virtual');
			this.dom.widget.classList.remove('convoca-idle');
			this.dom.input.focus();
			this.showSuggestions();
			this.scrollToBottom();
		}

		close() {
			if (!this.isOpen) return;
			this.isOpen = false;
			this.dom.container.classList.remove('convoca-open');
			this.dom.container.setAttribute('aria-hidden', 'true');
			this.dom.toggle.setAttribute('aria-label', 'Abrir asistente virtual');
			this.dom.toggle.focus();
		}

		toggle() {
			this.isOpen ? this.close() : this.open();
		}

		/* ── Send message ───────────────────────── */

		async send(text) {
			const query = (text || this.dom.input.value).trim();
			if (!query || !this.chat?.ready) return;

			this.lastQuery = query;
			this.dom.input.value = '';
			this.hideSuggestions();

			this.addMessage(query, 'user');

			// Greeting detection — respond without search.
			const greetings = [
				'hola', 'buenos días', 'buenos dias', 'buenas tardes', 'buenas noches',
				'hey', 'hello', 'hi', 'saludos', 'qué tal', 'que tal', 'buenass',
			];
			const normalized = query.toLowerCase().trim().replace(/[¿?!¡,.]/g, '');
			if (greetings.includes(normalized) || greetings.some(g => normalized === g) || greetings.some(g => normalized.startsWith(g + ' '))) {
				const name = this.config.settings?.title || 'Asistente Virtual';
				this.addBotMessage(`¡Hola! Soy ${name}. ¿En qué puedo ayudarte? 😊`, '');
				this.logInteraction(query, [], 0);
				return;
			}

			const typingMsg = this.showTyping();

			const start = performance.now();
			const { results, clusters, related } = this.chat.search(query);
			const elapsed = Math.round(performance.now() - start);

			if (typingMsg) typingMsg.remove();

			// Record in session
			const resultIds = results.map(r => r.entry.id);
			this.session.addQuery(query, resultIds);

			if (results.length > 0) {
				// Show context message from session
				const ctxMsg = this.session.getContextMessage(results);
				if (ctxMsg) {
					this.addBotMessage(ctxMsg, 'convoca-context');
				}

				// Show clustered results
				this.showClusteredResults(clusters, results, query, elapsed);

				// Show related content from graph
				this.showRelatedContent(related, results[0].entry);
			} else {
				this.showNoResults(query);
			}

			this.logInteraction(query, results, elapsed);
		}

		/**
		 * Show clustered results.
		 * @param {Array} clusters
		 * @param {Array} results
		 * @param {string} query
		 * @param {number} elapsed
		 */
		showClusteredResults(clusters, results, query, elapsed) {
			const hasMultipleThemes = clusters.length > 1;

			if (hasMultipleThemes && clusters.length >= 2) {
				// Hybrid response
				const sourceCount = results.length;
				const div = document.createElement('div');
				div.className = 'convoca-message convoca-message-bot convoca-hybrid';

				let html = `<div class="convoca-message-text"><p><strong>He encontrado información en ${sourceCount} fuentes:</strong></p></div>`;

				for (const cluster of clusters) {
					html += `<div class="convoca-cluster"><div class="convoca-cluster-theme">📂 ${this.escapeHtml(cluster.theme)}</div>`;
					for (const entry of cluster.entries) {
						const sourceLabel = entry.type === 'convoca_faq' ? 'FAQ' :
						                   entry.type === 'convoca_kb' ? 'Base de Conocimiento' :
						                   entry.type === 'post' ? 'Blog' :
						                   entry.type === 'page' ? 'Página' :
						                   entry.type === 'product' ? 'Producto' : entry.type;
						html += `<div class="convoca-cluster-entry">
							<a href="${this.escapeHtml(entry.url || '#')}" target="_blank" rel="noopener">
							📄 <strong>${this.escapeHtml(entry.title)}</strong></a>
							<span class="convoca-cluster-meta">(${sourceLabel})</span>
						</div>`;
					}
					html += '</div>';
				}

				div.innerHTML = html;
				this.dom.messages.appendChild(div);
				this.scrollToBottom();

			} else {
				// Single result
				this.showResultEntry(results[0], query);
			}
		}

		/**
		 * Show a single result entry.
		 * @param {Object} result
		 * @param {string} query
		 */
		showResultEntry(result, query) {
			const entry = result.entry;
			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-result';

			const text = this.truncate(entry.excerpt || entry.content, 350);
			const renderedContent = this.renderMarkdown(text);
			const sourceLink = entry.url
				? `<div class="convoca-message-source"><a href="${this.escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer">→ ${this.escapeHtml(entry.title)}</a></div>`
				: '';

			div.innerHTML = `<div class="convoca-message-text">${renderedContent}</div>${sourceLink}
				<div class="convoca-message-actions">
					<button class="convoca-action-feedback" data-vote="up" data-query="${this.escapeHtml(query)}" data-id="${entry.id}" data-score="${result.score}" title="Me sirvió">👍</button>
					<button class="convoca-action-feedback" data-vote="down" data-query="${this.escapeHtml(query)}" title="No me sirvió">👎</button>
					<button class="convoca-action-copy" title="Copiar respuesta">📋</button>
				</div>`;

			this.dom.messages.appendChild(div);
			this.scrollToBottom();
			this.bindResultActions(div, query, entry, result.score);
		}

		/**
		 * Show related content from the knowledge graph.
		 * @param {Array} related
		 * @param {Object} currentEntry
		 */
		showRelatedContent(related, currentEntry) {
			if (related.length === 0) return;

			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-related-section';
			let html = `<div class="convoca-message-text"><p>🔗 <strong>Contenido relacionado:</strong></p></div>`;

			for (const rel of related) {
				const relType = rel.type === 'same_category' ? 'misma categoría' :
				                rel.type === 'same_tag' ? 'mismas etiquetas' :
				                rel.type === 'internal_link' ? 'enlace interno' : 'relacionado';
				html += `<div class="convoca-related-entry">
					<a href="${this.escapeHtml(rel.entry.url || '#')}" target="_blank" rel="noopener">
					📎 ${this.escapeHtml(rel.entry.title)}</a>
					<span class="convoca-related-meta">(${relType})</span>
				</div>`;
			}

			div.innerHTML = html;
			this.dom.messages.appendChild(div);
			this.scrollToBottom();
		}

		/**
		 * Add a bot message.
		 * @param {string} text
		 * @param {string} cls
		 */
		addBotMessage(text, cls = '') {
			const div = document.createElement('div');
			div.className = `convoca-message convoca-message-bot ${cls}`;
			div.textContent = text;
			this.dom.messages.appendChild(div);
			this.scrollToBottom();
		}

		/* ── Message rendering ──────────────────── */

		addMessage(text, sender) {
			const div = document.createElement('div');
			div.className = `convoca-message convoca-message-${sender}`;

			if (sender === 'user') {
				div.textContent = text;
			}

			this.dom.messages.appendChild(div);
			this.messageCount++;
			this.scrollToBottom();
			return div;
		}

		showTyping() {
			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-typing';
			div.id = 'convoca-typing';
			div.setAttribute('aria-label', 'Escribiendo…');
			div.innerHTML = '<span></span><span></span><span></span>';
			this.dom.messages.appendChild(div);
			this.scrollToBottom();
			return div;
		}

		showResults(results, query) {
			const top = results[0];
			const entry = top.entry;

			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-result';

			// Content with markdown-like rendering
			const text = this.truncate(entry.excerpt || entry.content, 350);
			const renderedContent = this.renderMarkdown(text);

			// Source link
			const sourceLink = entry.url
				? `<div class="convoca-message-source"><a href="${this.escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer">→ ${this.escapeHtml(entry.title)}</a></div>`
				: '';

			// Action buttons
			const actions = `
				<div class="convoca-message-actions">
					<button class="convoca-action-feedback" data-vote="up" data-query="${this.escapeHtml(query)}" data-id="${entry.id}" data-score="${top.score}" title="Me sirvió">👍</button>
					<button class="convoca-action-feedback" data-vote="down" data-query="${this.escapeHtml(query)}" title="No me sirvió">👎</button>
					<button class="convoca-action-copy" title="Copiar respuesta">📋</button>
				</div>`;

			div.innerHTML = `<div class="convoca-message-text">${renderedContent}</div>${sourceLink}${actions}`;

			this.dom.messages.appendChild(div);
			this.scrollToBottom();
			this.bindResultActions(div, query, entry, top.score);

			// Show "maybe also" suggestions
			if (results.length > 1) {
				this.showRelated(results.slice(1, 3), query);
			}
		}

		showNoResults(query) {
			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-noresult';
			div.innerHTML = `<div class="convoca-message-text">${this.escapeHtml(this.config.i18n?.noResults || 'No encontré una respuesta. Reformula la pregunta o contacta con nosotros.')}</div>
				<div class="convoca-message-actions">
					<button class="convoca-action-feedback" data-vote="down" data-query="${this.escapeHtml(query)}" title="Reportar">📝 Reportar</button>
				</div>`;
			this.dom.messages.appendChild(div);
			this.scrollToBottom();
		}

		showMaintenance() {
			this.dom.toggle.style.display = 'none';
			const msg = document.createElement('div');
			msg.className = 'convoca-maintenance-msg';
			msg.textContent = this.config.i18n.maintenance;
			document.body.appendChild(msg);
		}

		showError(msg) {
			if (this.dom.messages) {
				const div = document.createElement('div');
				div.className = 'convoca-message convoca-message-bot convoca-error';
				div.textContent = msg;
				this.dom.messages.appendChild(div);
			}
		}

		showStatus(msg) {
			if (this.dom.messages) {
				const div = document.createElement('div');
				div.className = 'convoca-message convoca-message-bot convoca-loading';
				div.id = 'convoca-loading';
				div.textContent = msg;
				this.dom.messages.appendChild(div);
			}
		}

		hideStatus() {
			const el = document.getElementById('convoca-loading');
			if (el) el.remove();
		}

		/* ── Suggestions ─────────────────────────── */

		showSuggestions() {
			const container = this.dom.suggestions;
			if (!container) return;

			// Only show suggestions if there are few messages
			if (this.messageCount > 2) {
				container.style.display = 'none';
				return;
			}

			const suggestions = [
				'¿Cómo funciona?',
				'¿Qué servicios ofrecéis?',
				'¿Cómo puedo contactar?',
			];

			container.innerHTML = '<div class="convoca-suggestions-label">Sugerencias:</div>' +
				suggestions.map(s =>
					`<button class="convoca-suggestion-chip" data-query="${this.escapeHtml(s)}">${this.escapeHtml(s)}</button>`
				).join('');

			container.style.display = 'block';
			container.querySelectorAll('.convoca-suggestion-chip').forEach(btn => {
				btn.addEventListener('click', () => this.send(btn.dataset.query));
			});
		}

		hideSuggestions() {
			if (this.dom.suggestions) {
				this.dom.suggestions.style.display = 'none';
			}
		}

		showRelated(related, query) {
			if (related.length === 0) return;

			const div = document.createElement('div');
			div.className = 'convoca-message convoca-message-bot convoca-related';
			div.innerHTML = '<div style="font-size:12px;color:#6b7280;margin-bottom:6px;">Quizás también te interese:</div>' +
				related.map(r =>
					`<button class="convoca-related-chip" data-query="${this.escapeHtml(r.entry.title)}">${this.escapeHtml(r.entry.title)}</button>`
				).join('');

			this.dom.messages.appendChild(div);
			this.scrollToBottom();

			div.querySelectorAll('.convoca-related-chip').forEach(btn => {
				btn.addEventListener('click', () => this.send(btn.dataset.query));
			});
		}

		/* ── Action buttons ──────────────────────── */

		bindResultActions(div, query, entry, score) {
			const id = entry.id;

			// Feedback
			div.querySelectorAll('.convoca-action-feedback').forEach(btn => {
				btn.addEventListener('click', () => {
					const vote = btn.dataset.vote;
					this.logFeedback(query, id, score, vote);
					btn.textContent = vote === 'up' ? '✅' : '👎✅';
					btn.disabled = true;
					btn.parentElement.querySelectorAll('.convoca-action-feedback').forEach(b => b.disabled = true);
				});
			});

			// Copy
			const copyBtn = div.querySelector('.convoca-action-copy');
			if (copyBtn) {
				copyBtn.addEventListener('click', () => {
					const text = entry.excerpt || entry.content || '';
					navigator.clipboard.writeText(text.substring(0, 1000)).then(() => {
						copyBtn.textContent = '✅';
						setTimeout(() => { copyBtn.textContent = '📋'; }, 2000);
					}).catch(() => {
						// Fallback
						const ta = document.createElement('textarea');
						ta.value = text.substring(0, 1000);
						document.body.appendChild(ta);
						ta.select();
						document.execCommand('copy');
						ta.remove();
						copyBtn.textContent = '✅';
					});
				});
			}
		}

		/* ── Markdown-like renderer ──────────────── */

		renderMarkdown(text) {
			let html = this.escapeHtml(text);

			// Bold **text**
			html = html.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );

			// Italic *text*
			html = html.replace( /\*(.+?)\*/g, '<em>$1</em>' );

			// Inline code `code`
			html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );

			// Links [text](url) — only allow http/https/mailto
			html = html.replace(
				/\[([^\]]+)\]\(([^)]+)\)/g,
				function ( match, text, url ) {
					url = url.trim();
					if ( /^(https?:\/\/|mailto:|\/|#)/.test( url ) ) {
						return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + text + '</a>';
					}
					// Unsafe URL → render as plain text
					return text + ' (' + url + ')';
				}
			);

			// Unordered lists
			html = html.replace(/^[\s]*[-*+]\s+(.+)$/gm, '<li>$1</li>');
			html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');

			// Ordered lists
			html = html.replace(/^[\s]*\d+\.\s+(.+)$/gm, '<li>$1</li>');
			html = html.replace(/(?:^|\n)(<li>.*<\/li>\n?)+(?=\n|$)/g, '<ol>$&</ol>');

			// Blockquotes
			html = html.replace(/^&gt;\s+(.+)$/gm, '<blockquote>$1</blockquote>');

			// Line breaks: double newline = paragraph
			html = html.replace(/\n\n/g, '</p><p>');

			// Single newlines
			html = html.replace(/\n/g, '<br>');

			return `<p>${html}</p>`;
		}

		/* ── Input handling ──────────────────────── */

		onInputKey(e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				this.send();
			}
		}

		onGlobalKey(e) {
			// Escape to close
			if (e.key === 'Escape' && this.isOpen) {
				this.close();
			}
		}

		onScroll() {
			const el = this.dom.messages;
			const threshold = el.scrollHeight - el.clientHeight - 60;
			this.userScrolledUp = el.scrollTop < threshold;
		}

		/* ── Scroll trigger for auto-open ────────── */

		initScrollTrigger() {
			let triggered = false;
			const handler = () => {
				if (triggered) return;
				const scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight) * 100;
				const threshold = this.config.settings?.autoOpenScroll || 50;
				if (scrollPercent >= threshold) {
					triggered = true;
					this.open();
					window.removeEventListener('scroll', handler);
				}
			};
			window.addEventListener('scroll', handler, { passive: true });
		}

		/* ── Idle pulse ──────────────────────────── */

		startIdlePulse() {
			if (this.isOpen) return;
			let pulseCount = 0;
			const interval = setInterval(() => {
				if (this.isOpen) { clearInterval(interval); return; }
				pulseCount++;
				this.dom.widget.classList.add('convoca-idle');

				// Stop after 3 pulses (30s total)
				if (pulseCount >= 3) {
					clearInterval(interval);
					setTimeout(() => this.dom.widget.classList.remove('convoca-idle'), 10000);
				}
			}, 10000);
		}

		/* ── Helpers ─────────────────────────────── */

		scrollToBottom() {
			if (this.userScrolledUp) return;
			requestAnimationFrame(() => {
				this.dom.messages.scrollTop = this.dom.messages.scrollHeight;
			});
		}

		escapeHtml(text) {
			const d = document.createElement('div');
			d.textContent = text;
			return d.innerHTML;
		}

		truncate(text, max) {
			return text && text.length > max ? text.substring(0, max) + '…' : (text || '');
		}

		/* ── Logging ─────────────────────────────── */

		async logInteraction(query, results, timeMs) {
			if (!this.config.restUrl) return;
			const data = {
				query, response_found: results.length > 0,
				response_id: results.length > 0 ? results[0].entry.id : null,
				score: results.length > 0 ? results[0].score : 0,
				clicked: false, time_ms: timeMs, page_url: window.location.href,
			};
			try { await fetch(this.config.restUrl + 'log', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
				body: JSON.stringify(data),
			}); } catch (e) { /* silent */ }
		}

		async logFeedback(query, responseId, score, vote) {
			if (!this.config.restUrl) return;
			try { await fetch(this.config.restUrl + 'log', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
				body: JSON.stringify({
					query, response_found: true, response_id: responseId,
					score, clicked: vote === 'up', time_ms: 0, page_url: window.location.href,
				}),
			}); } catch (e) { /* silent */ }
		}
	}

	// Auto-init
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => new ConvocaWidget().init());
	} else {
		new ConvocaWidget().init();
	}
})();
