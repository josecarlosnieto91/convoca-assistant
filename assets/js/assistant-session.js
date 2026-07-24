/**
 * Convoca Assistant — Session Memory
 *
 * Browser-only localStorage session tracking for conversational context.
 * No data leaves the browser. Fully GDPR compliant.
 *
 * @module ConvocaAssistant/Session
 */

(function () {
	'use strict';

	const STORAGE_KEY = 'convoca_assistant_session';
	const MAX_QUERIES = 50;

	class SessionMemory {
		constructor() {
			this.data = this.load();
		}

		/**
		 * Load session from localStorage.
		 * @returns {{sessionId: string, queries: Array, topics: string[], clicked: number[], dismissed: number[]}}
		 */
		load() {
			try {
				const raw = localStorage.getItem(STORAGE_KEY);
				if (raw) {
					const data = JSON.parse(raw);
					if (data && data.sessionId) return data;
				}
			} catch (e) { /* corrupted storage */ }

			return {
				sessionId: this.generateId(),
				queries: [],
				topics: [],
				clicked: [],
				dismissed: [],
			};
		}

		/**
		 * Save session to localStorage.
		 */
		save() {
			try {
				localStorage.setItem(STORAGE_KEY, JSON.stringify(this.data));
			} catch (e) { /* quota exceeded */ }
		}

		/**
		 * Generate a unique session ID.
		 * @returns {string}
		 */
		generateId() {
			return 'sess_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2, 8);
		}

		/**
		 * Record a query.
		 * @param {string} query - The user's query
		 * @param {number[]} resultIds - IDs of returned results
		 */
		addQuery(query, resultIds) {
			this.data.queries.push({
				text: query,
				resultIds: resultIds,
				timestamp: Date.now(),
			});

			// Trim old queries
			if (this.data.queries.length > MAX_QUERIES) {
				this.data.queries = this.data.queries.slice(-MAX_QUERIES);
			}

			// Update topics (extract keywords from query)
			const words = query.toLowerCase().split(/\s+/).filter(w => w.length > 3);
			this.data.topics = [...new Set([...this.data.topics, ...words])];

			// Keep only last 20 topics
			if (this.data.topics.length > 20) {
				this.data.topics = this.data.topics.slice(-20);
			}

			this.save();
		}

		/**
		 * Mark an entry as clicked.
		 * @param {number} id
		 */
		markClicked(id) {
			if (!this.data.clicked.includes(id)) {
				this.data.clicked.push(id);
				this.save();
			}
		}

		/**
		 * Mark an entry as dismissed (not useful).
		 * @param {number} id
		 */
		markDismissed(id) {
			if (!this.data.dismissed.includes(id)) {
				this.data.dismissed.push(id);
				this.save();
			}
		}

		/**
		 * Get IDs the user has already seen (clicked + result pages).
		 * @returns {number[]}
		 */
		getSeenIds() {
			const seen = new Set([...this.data.clicked, ...this.data.dismissed]);
			for (const q of this.data.queries) {
				for (const id of q.resultIds) {
					seen.add(id);
				}
			}
			return [...seen];
		}

		/**
		 * Get the last N queries.
		 * @param {number} n
		 * @returns {Array}
		 */
		getRecentQueries(n = 3) {
			return this.data.queries.slice(-n);
		}

		/**
		 * Get context-aware suggestions based on session history.
		 * @param {Array} currentResults - Current search results
		 * @returns {string|null} Contextual message or null
		 */
		getContextMessage(currentResults) {
			const recent = this.getRecentQueries(2);
			// Need at least 2 queries for meaningful context.
			if (recent.length < 2) return null;

			// Only show context if at least 2 queries in the last 10 minutes.
			const now = Date.now();
			const recentQueries = this.data.queries.filter( q => ( now - q.timestamp ) < 600000 );
			if ( recentQueries.length < 2 ) return null;
			const prevQuery = recentQueries[recentQueries.length - 2].text;
			const currentTopics = this.data.topics.slice(-5);

			// If the user asked about a similar topic before
			if (currentTopics.length > 1) {
				return `Antes preguntaste sobre "${prevQuery.substring(0, 40)}…". También puede interesarte explorar contenido relacionado.`;
			}

			return null;
		}

		/**
		 * Clear all session data.
		 */
		clear() {
			this.data = {
				sessionId: this.generateId(),
				queries: [],
				topics: [],
				clicked: [],
				dismissed: [],
			};
			this.save();
		}
	}

	window.ConvocaSession = SessionMemory;
})();
