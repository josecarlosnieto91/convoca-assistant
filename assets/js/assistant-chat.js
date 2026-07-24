/**
 * Convoca Assistant — Chat Engine
 *
 * Core search engine running Fuse.js in the browser.
 * Handles index loading, query normalization, synonym expansion,
 * composite scoring (fuzzy + exact + weight + recency), and ranking.
 *
 * @module ConvocaAssistant/Chat
 */

(function () {
	'use strict';

	/* ── Constants ─────────────────────────────── */

	/** @const {number} Minimum score threshold to return a result */
	const SCORE_THRESHOLD = 0.10;

	/** @const {Object<string,number>} Score component weights */
	const WEIGHTS = {
		fuzzy:   0.50,
		exact:   0.15,
		synonym: 0.10,
		stem:    0.05,
		coverage: 0.10,
		recency: 0.05,
		weight:  0.05,
	};

	/* ── Spanish stemmer (light) ───────────────── */

	/**
	 * Light Spanish stemming: removes common suffixes.
	 * Not a full morphological analyzer, but good enough for search matching.
	 *
	 * @param {string} word
	 * @returns {string}
	 */
	function stemSpanish(word) {
		if (word.length < 4) return word;

		const suffixes = [
			// Verb conjugations
			/ando$|iendo$|ando[ns]?$/i,
			/aba$|abas$|ábamos$|abais$|aban$/i,
			/ía$|ías$|íamos$|íais$|ían$/i,
			/é$|aste$|ó$|amos$|asteis$|aron$/i,
			/eré$|erás$|erá$|eremos$|eréis$|erán$/i,
			/iré$|irás$|irá$|iremos$|iréis$|irán$/i,
			/ando$|iendo$/i,
			/ado$|ido$|ada$|ida$/i,
			/ar$|er$|ir$/i,
			// Noun/adjective suffixes
			/ción$|ciones$/i,
			/miento$|mientos$/i,
			/mente$/i,
			/dor$|dora$|dores$|doras$/i,
			/ero$|era$|eros$|eras$/i,
			/ista$|istas$/i,
			/azo$|aza$|azos$|azas$/i,
			/ito$|ita$|itos$|itas$/i,
			/ón$|ona$|ones$/i,
			/ble$|bles$/i,
			/eza$|ezas$/i,
			/ivo$|iva$|ivos$|ivas$/i,
		];

		let stemmed = word;
		for (const pattern of suffixes) {
			const candidate = stemmed.replace(pattern, '');
			if (candidate !== word && candidate.length >= 3) {
				stemmed = candidate;
				break;
			}
		}

		return stemmed.toLowerCase();
	}

	/* ── Main engine ───────────────────────────── */

	class ConvocaChat {
		constructor() {
			/** @type {Fuse|null} */
			this.fuse = null;

			/** @type {Object|null} */
			this.index = null;

			/** @type {Object} */
			this.config = window.convocaAssistant || {};

			/** @type {boolean} */
			this.ready = false;
		}

		/**
		 * Initialize the engine: fetch and index the knowledge base.
		 * @returns {Promise<boolean>}
		 */
		async init() {
			try {
				const url = this.config.indexUrl || '/wp-content/uploads/convoca-assistant/index.json';

				const response = await fetch(url);
				if (!response.ok) throw new Error(`HTTP ${response.status}`);

				this.index = await response.json();
				if (!this.index || !this.index.entries || !Array.isArray(this.index.entries)) {
					throw new Error('Invalid index format');
				}

				const weights = this.config.settings?.weights || {};
				const threshold = this.config.settings?.threshold || 0.4;
				const distance = this.config.settings?.distance || 100;

				const options = {
					includeScore: true,
					shouldSort: false, // We'll sort ourselves with composite score
					threshold,
					distance,
					keys: [
						{ name: 'title',     weight: weights.title     || 4 },
						{ name: 'keywords',  weight: weights.keywords  || 3 },
						{ name: 'categories',weight: weights.categories|| 2 },
						{ name: 'content',   weight: weights.content   || 1 },
						{ name: 'tags',      weight: weights.tags      || 1 },
					],
				};

				this.fuse = new Fuse(this.index.entries, options);
				this.ready = true;
				return true;

			} catch (err) {
				console.error('[Convoca Assistant] Failed to load index:', err);
				this.ready = false;
				return false;
			}
		}

		/**
		 * Search the knowledge base with composite scoring.
		 * @param {string} query - User's raw query
		 * @returns {Array<{entry: Object, score: number}>}
		 */
		search(query) {
			if (!this.ready || !this.fuse || !this.index) {
				return [];
			}

			const maxResults = this.config.settings?.maxResults || 10;
			const synonyms = this.index.synonyms || {};
			const stopWords = this.index.stop_words || [];

			// 1. Normalize query
			const normalized = this.normalize(query);

			// 2. Tokenize and remove stop words
			const tokens = this.tokenize(normalized, stopWords);
			if (tokens.length === 0) return [];

			// 3. Expand with synonyms
			const expanded = this.expandSynonyms(tokens, synonyms);

			// 4. Run Fuse.js search (use expanded terms as search query)
			const fuseQuery = expanded.join(' ');
			const rawResults = this.fuse.search(fuseQuery);

			// 5. Calculate composite score for each result
			const scored = rawResults.map(result => {
				const entry = result.item;
				const fuzzyScore = 1 - (result.score || 0);
				const composite = this.compositeScore(entry, normalized, tokens, expanded, fuzzyScore);

				return {
					entry,
					score: composite,
				};
			});

			// 6. Filter by threshold
			const filtered = scored.filter(r => r.score >= SCORE_THRESHOLD);

			// 7. Sort by composite score descending
			filtered.sort((a, b) => b.score - a.score);

			return filtered.slice(0, maxResults);
		}

		/* ── Composite score ────────────────────── */

		/**
		 * Calculate the composite score for a single entry.
		 * @param {Object} entry     - Index entry
		 * @param {string} normalized - Normalized query
		 * @param {string[]} tokens  - Tokenized words
		 * @param {string[]} expanded - Words + synonyms
		 * @param {number} fuzzyScore - Raw Fuse.js score (inverted)
		 * @returns {number} Final score (0-1)
		 */
		compositeScore(entry, normalized, tokens, expanded, fuzzyScore) {
			const title    = (entry.title    || '').toLowerCase();
			const content  = (entry.content  || '').toLowerCase();
			const keywords = (entry.keywords || []).join(' ').toLowerCase();
			const cats     = (entry.categories||[]).join(' ').toLowerCase();
			const tags     = (entry.tags     || []).join(' ').toLowerCase();
			const excerpt  = (entry.excerpt  || '').toLowerCase();

			// Exact match bonus
			const exactBonus = this.exactMatchBonus(normalized, title, keywords, content);

			// Synonym bonus
			const synBonus = this.synonymBonus(content + ' ' + title, tokens, expanded);

			// Stem matching
			const stemBonus = this.stemBonus(tokens, title, content, keywords);

			// Content coverage
			const coverage = this.coverageScore(tokens, title, content, keywords, cats, tags, excerpt);

			// Recency
			const recency = this.recencyBonus(entry.date || entry.modified || '');

			// Weight factor
			const weight = entry.weight || 1.0;

			// Composite
			let score = (fuzzyScore * WEIGHTS.fuzzy)
			          + (exactBonus * WEIGHTS.exact)
			          + (synBonus   * WEIGHTS.synonym)
			          + (stemBonus  * WEIGHTS.stem)
			          + (coverage   * WEIGHTS.coverage)
			          + (recency    * WEIGHTS.recency)
			          + ((weight / 10.0) * WEIGHTS.weight);

			// Boost from weight multiplier
			score *= (0.5 + (weight / 20.0));

			return Math.min(score, 1.0);
		}

		/**
		 * Bonus for exact phrase matches.
		 * @param {string} query    - Normalized query
		 * @param {string} title    - Lowercase title
		 * @param {string} keywords - Lowercase keywords
		 * @param {string} content  - Lowercase content
		 * @returns {number} 0-0.15
		 */
		exactMatchBonus(query, title, keywords, content) {
			if (title.includes(query))    return 0.15;
			if (keywords.includes(query)) return 0.10;
			if (content.includes(query))  return 0.05;
			return 0;
		}

		/**
		 * Bonus when synonyms of query words appear in content.
		 * @param {string} text     - Combined text to search
		 * @param {string[]} tokens - Original words
		 * @param {string[]} expanded - Expanded words
		 * @returns {number} 0-0.10
		 */
		synonymBonus(text, tokens, expanded) {
			const extra = expanded.filter(w => !tokens.includes(w));
			if (extra.length === 0) return 0;

			const hits = extra.filter(syn => text.includes(syn)).length;
			return (hits / extra.length) * 0.10;
		}

		/**
		 * Bonus when stems of query words match stems in content.
		 * @param {string[]} tokens  - Query words
		 * @param {string}   title   - Lowercase title
		 * @param {string}   content - Lowercase content
		 * @param {string}   keywords - Lowercase keywords
		 * @returns {number} 0-0.05
		 */
		stemBonus(tokens, title, content, keywords) {
			const searchSpace = title + ' ' + content + ' ' + keywords;
			let stemHits = 0;

			for (const word of tokens) {
				const stem = stemSpanish(word);
				if (stem.length < 3) continue;
				if (searchSpace.includes(stem)) stemHits++;
			}

			return tokens.length > 0 ? (stemHits / tokens.length) * 0.05 : 0;
		}

		/**
		 * How many query words appear anywhere in the entry.
		 * @param {string[]} tokens   - Query words
		 * @param {string}   title    - Lowercase title
		 * @param {string}   content  - Lowercase content
		 * @param {string}   keywords - Lowercase keywords
		 * @param {string}   cats     - Lowercase categories
		 * @param {string}   tags     - Lowercase tags
		 * @param {string}   excerpt  - Lowercase excerpt
		 * @returns {number} 0-1
		 */
		coverageScore(tokens, title, content, keywords, cats, tags, excerpt) {
			const searchSpace = `${title} ${content} ${keywords} ${cats} ${tags} ${excerpt}`;
			if (tokens.length === 0) return 0;

			const matches = tokens.filter(w => searchSpace.includes(w)).length;
			return matches / tokens.length;
		}

		/**
		 * Bonus for recent content.
		 * @param {string} dateStr - MySQL date string
		 * @returns {number} 0-0.05
		 */
		recencyBonus(dateStr) {
			if (!dateStr) return 0;
			const date = new Date(dateStr);
			if (isNaN(date.getTime())) return 0;

			const days = (Date.now() - date.getTime()) / 86400000;

			if (days < 30)  return 0.05;
			if (days < 90)  return 0.03;
			if (days < 365) return 0.01;
			return 0;
		}

		/* ── Text processing ─────────────────────── */

		/**
		 * Normalize text: lower, remove accents, strip punctuation.
		 * @param {string} text
		 * @returns {string}
		 */
		normalize(text) {
			return text
				.toLowerCase()
				.normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Remove accents
				.replace(/[¿?!¡,.;:\-"'«»()\[\]{}]/g, ' ')       // Punctuation → space
				.replace(/\s+/g, ' ')                               // Collapse spaces
				.trim();
		}

		/**
		 * Tokenize and remove stop words.
		 * @param {string} text      - Normalized text
		 * @param {string[]} stopWords
		 * @returns {string[]}
		 */
		tokenize(text, stopWords) {
			return text
				.split(' ')
				.filter(w => w.length > 1 && !stopWords.includes(w));
		}

		/**
		 * Expand tokens with synonyms from the index dictionary.
		 * @param {string[]} tokens
		 * @param {Object<string, string[]>} synonyms
		 * @returns {string[]}
		 */
		expandSynonyms(tokens, synonyms) {
			const expanded = [...tokens];

			for (const token of tokens) {
				for (const [term, synList] of Object.entries(synonyms)) {
					const termLower = term.toLowerCase();
					const synLower  = synList.map(s => s.toLowerCase());

					if (token === termLower || synLower.includes(token)) {
						expanded.push(...synLower, termLower);
					}
				}
			}

			return [...new Set(expanded)];
		}
	}

	// Export
	window.ConvocaChat = ConvocaChat;
})();
