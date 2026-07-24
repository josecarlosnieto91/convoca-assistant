/**
 * Convoca Assistant — Chat Engine (KG Evolution)
 *
 * Fuse.js search engine with:
 * - Composite scoring: fuzzy + graph + exact + synonym + stem + coverage + recency + weight
 * - n-gram matching (unigram, bigram, trigram)
 * - Light Spanish lemmatization
 * - Result clustering by theme
 * - Knowledge graph integration (related content)
 * - Hybrid response rendering
 *
 * @module ConvocaAssistant/Chat
 */

(function () {
	'use strict';

	const SCORE_THRESHOLD = 0.10;

	/* ── Lemma dictionary (Spanish, compact) ─────── */

	const LEMMAS = {
		// Verb lemmas
		'inscribir': ['inscribo', 'inscribes', 'inscribe', 'inscribimos', 'inscribís', 'inscriben',
			           'inscribí', 'inscribiste', 'inscribió', 'inscribieron',
			           'inscribía', 'inscribías', 'inscribíamos', 'inscribíais', 'inscribían',
			           'inscribiré', 'inscribirás', 'inscribirá', 'inscribiremos', 'inscribiréis', 'inscribirán',
			           'inscrito', 'inscrita', 'inscritos', 'inscritas',
			           'inscripción', 'inscripciones', 'inscribirse'],
		'registrar': ['registro', 'registras', 'registra', 'registramos', 'registráis', 'registran',
			          'registré', 'registraste', 'registró', 'registraron',
			          'registraba', 'registrabas', 'registrábamos',
			          'registraré', 'registrarás', 'registrará', 'registraremos',
			          'registrado', 'registrada', 'registrados', 'registradas',
			          'registración', 'registraciones', 'registrarse'],
		'pagar': ['pago', 'pagas', 'paga', 'pagamos', 'pagáis', 'pagan',
			      'pagué', 'pagaste', 'pagó', 'pagaron',
			      'pagaba', 'pagabas', 'pagábamos',
			      'pagaré', 'pagarás', 'pagará', 'pagaremos',
			      'pagado', 'pagada', 'pagados', 'pagadas',
			      'pago', 'pagos', 'pagador', 'pagadora'],
		'renovar': ['renuevo', 'renuevas', 'renueva', 'renovamos', 'renováis', 'renuevan',
			        'renové', 'renovaste', 'renovó', 'renovaron',
			        'renovaba', 'renovabas', 'renovábamos',
			        'renovaré', 'renovarás', 'renovará', 'renovaremos',
			        'renovado', 'renovada', 'renovados', 'renovadas',
			        'renovación', 'renovaciones', 'renovable', 'renovables'],
		'solicitar': ['solicito', 'solicitas', 'solicita', 'solicitamos', 'solicitáis', 'solicitan',
			          'solicité', 'solicitaste', 'solicitó', 'solicitaron',
			          'solicitaba', 'solicitabas', 'solicitábamos',
			          'solicitaré', 'solicitarás', 'solicitará', 'solicitaremos',
			          'solicitado', 'solicitada', 'solicitados', 'solicitadas',
			          'solicitud', 'solicitudes', 'solicitante', 'solicitantes'],
		'contactar': ['contacto', 'contactas', 'contacta', 'contactamos', 'contactáis', 'contactan',
			          'contacté', 'contactaste', 'contactó', 'contactaron',
			          'contactaba', 'contactabas', 'contactábamos',
			          'contactaré', 'contactarás', 'contactará', 'contactaremos',
			          'contactado', 'contactada', 'contactados', 'contactadas',
			          'contacto', 'contactos'],
		'participar': ['participo', 'participas', 'participa', 'participamos', 'participáis', 'participan',
			           'participé', 'participaste', 'participó', 'participaron',
			           'participaba', 'participabas', 'participábamos',
			           'participaré', 'participarás', 'participará', 'participaremos',
			           'participado', 'participada', 'participados', 'participadas',
			           'participación', 'participaciones', 'participante', 'participantes'],
		'gestionar': ['gestiono', 'gestionas', 'gestiona', 'gestionamos', 'gestionáis', 'gestionan',
			          'gestioné', 'gestionaste', 'gestionó', 'gestionaron',
			          'gestionaba', 'gestionabas', 'gestionábamos',
			          'gestionaré', 'gestionarás', 'gestionará', 'gestionaremos',
			          'gestionado', 'gestionada', 'gestionados', 'gestionadas',
			          'gestión', 'gestiones', 'gestor', 'gestora'],
		'descargar': ['descargo', 'descargas', 'descarga', 'descargamos', 'descargáis', 'descargan',
			          'descargué', 'descargaste', 'descargó', 'descargaron',
			          'descargaba', 'descargabas', 'descargábamos',
			          'descargaré', 'descargarás', 'descargará', 'descargaremos',
			          'descargado', 'descargada', 'descargados', 'descargadas',
			          'descarga', 'descargas', 'descargable', 'descargables'],
		'organizar': ['organizo', 'organizas', 'organiza', 'organizamos', 'organizáis', 'organizan',
			          'organicé', 'organizaste', 'organizó', 'organizaron',
			          'organizaba', 'organizabas', 'organizábamos',
			          'organizaré', 'organizarás', 'organizará', 'organizaremos',
			          'organizado', 'organizada', 'organizados', 'organizadas',
			          'organización', 'organizaciones', 'organizador', 'organizadora',
			          'organizativo', 'organizativa'],
		'necesitar': ['necesito', 'necesitas', 'necesita', 'necesitamos', 'necesitáis', 'necesitan',
			          'necesité', 'necesitaste', 'necesitó', 'necesitaron',
			          'necesitaba', 'necesitabas', 'necesitábamos',
			          'necesitaré', 'necesitarás', 'necesitará', 'necesitaremos',
			          'necesitado', 'necesitada', 'necesitados', 'necesitadas',
			          'necesidad', 'necesidades', 'necesario', 'necesaria'],
		// Noun lemmas
		'socio': ['socia', 'socios', 'socias', 'asociado', 'asociada', 'asociados', 'asociadas',
			      'asociacion', 'asociaciones', 'asociarse', 'asociar'],
		'documento': ['documentos', 'documental', 'documentación', 'documentado', 'documentada'],
		'cuota': ['cuotas', 'cuotificar', 'cuotificación'],
		'taller': ['talleres', 'tallerista', 'talleristas'],
		'horario': ['horarios', 'horaria', 'horarias', 'horario', 'hora', 'horas'],
		'precio': ['precios', 'preciar', 'preciado', 'coste', 'costes', 'costo', 'costos', 'tarifa', 'tarifas'],
		'curso': ['cursos', 'cursar', 'cursado', 'cursando'],
		'actividad': ['actividades', 'activo', 'activa', 'activos', 'activas', 'activar', 'activado'],
		'formulario': ['formularios', 'formular', 'formulado', 'formación'],
		'voluntario': ['voluntaria', 'voluntarios', 'voluntarias', 'voluntariado'],
		'membresía': ['membresías', 'membresia', 'membresias', 'membersía', 'membersias'],
		'beneficio': ['beneficios', 'beneficiar', 'beneficiado', 'beneficiaria', 'beneficiario'],
		'evento': ['eventos', 'eventual'],
		'proyecto': ['proyectos', 'proyectar', 'proyectado'],
		'certificado': ['certificados', 'certificar', 'certificada', 'certificación', 'certificaciones'],
	};

	/* ── n-gram generator ────────────────────────── */

	/**
	 * Generate n-grams from tokens.
	 * @param {string[]} tokens
	 * @param {number} maxN
	 * @returns {{unigrams: string[], bigrams: string[], trigrams: string[]}}
	 */
	function generateNgrams(tokens, maxN = 3) {
		const result = { unigrams: tokens };

		if (maxN >= 2) {
			result.bigrams = [];
			for (let i = 0; i < tokens.length - 1; i++) {
				result.bigrams.push(tokens[i] + ' ' + tokens[i + 1]);
			}
		}

		if (maxN >= 3) {
			result.trigrams = [];
			for (let i = 0; i < tokens.length - 2; i++) {
				result.trigrams.push(tokens[i] + ' ' + tokens[i + 1] + ' ' + tokens[i + 2]);
			}
		}

		return result;
	}

	/* ── Lemmatizer ──────────────────────────────── */

	/**
	 * Find the lemma (canonical form) of a word.
	 * @param {string} word - Lowercase word
	 * @returns {string} Lemma or word itself if not found
	 */
	function lemmatize(word) {
		if (word.length < 3) return word;

		// Direct lookup in lemma dictionary values → find the key
		for (const [lemma, forms] of Object.entries(LEMMAS)) {
			if (lemma === word) return lemma;
			for (const form of forms) {
				if (form === word) return lemma;
			}
		}

		return word;
	}

	/**
	 * Expand a word with lemmas for more matching.
	 * @param {string} word
	 * @returns {string[]} [original, lemma, ...other forms]
	 */
	function expandWithLemmas(word) {
		const results = [word];
		const lemma = lemmatize(word);
		if (lemma !== word) {
			results.push(lemma);
			// Add a few representative forms
			const forms = LEMMAS[lemma];
			if (forms) {
				// Add first 3 forms as examples
				for (let i = 0; i < Math.min(3, forms.length); i++) {
					if (forms[i] !== word && forms[i] !== lemma) {
						results.push(forms[i]);
					}
				}
			}
		}
		return [...new Set(results)];
	}

	/* ── Clustering ──────────────────────────────── */

	/**
	 * Cluster search results by theme (category).
	 * @param {Array} results - Scored results
	 * @returns {Array} Clusters
	 */
	function clusterResults(results) {
		const clusters = [];
		const seen = new Set();

		for (const result of results) {
			const entry = result.entry;
			const cats = entry.categories || [];

			// Find or create cluster by first category
			let clusterKey = cats.length > 0 ? cats[0] : 'general';
			let cluster = clusters.find(c => c.theme === clusterKey);

			if (!cluster) {
				cluster = { theme: clusterKey, entries: [], scores: [] };
				clusters.push(cluster);
			}

			if (!seen.has(entry.id)) {
				cluster.entries.push(entry);
				cluster.scores.push(result.score);
				seen.add(entry.id);
			}
		}

		// Sort clusters by average score, limit to 3 clusters, 3 entries each
		for (const c of clusters) {
			c.avgScore = c.scores.reduce((a, b) => a + b, 0) / c.scores.length;
			c.entries = c.entries.slice(0, 3);
		}

		return clusters
			.sort((a, b) => b.avgScore - a.avgScore)
			.slice(0, 3);
	}

	/* ── Main engine ─────────────────────────────── */

	class ConvocaChat {
		constructor() {
			this.fuse = null;
			this.index = null;
			this.graph = null;
			this.config = window.convocaAssistant || {};
			this.ready = false;
		}

		/**
		 * Initialize: fetch index + graph.
		 * @returns {Promise<boolean>}
		 */
		async init() {
			try {
				const url = this.config.indexUrl || '/wp-content/uploads/convoca-assistant/index.json';
				const response = await fetch(url);
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				this.index = await response.json();
				if (!this.index || !this.index.entries) throw new Error('Invalid index');

				// Load knowledge graph
				await this.loadGraph();

				const weights = this.config.settings?.weights || {};
				const threshold = this.config.settings?.threshold || 0.4;
				const distance = this.config.settings?.distance || 100;

				this.fuse = new Fuse(this.index.entries, {
					includeScore: true,
					shouldSort: false,
					threshold,
					distance,
					keys: [
						{ name: 'title',     weight: weights.title     || 4 },
						{ name: 'keywords',  weight: weights.keywords  || 3 },
						{ name: 'categories',weight: weights.categories|| 2 },
						{ name: 'content',   weight: weights.content   || 1 },
						{ name: 'tags',      weight: weights.tags      || 1 },
					],
				});

				this.ready = true;
				return true;
			} catch (err) {
				console.error('[Convoca Assistant] Init error:', err);
				this.ready = false;
				return false;
			}
		}

		/**
		 * Load graph.json for related content.
		 */
		async loadGraph() {
			try {
				const baseUrl = this.config.indexUrl.substring(0, this.config.indexUrl.lastIndexOf('/'));
				const graphUrl = baseUrl + '/graph.json';
				const res = await fetch(graphUrl);
				if (res.ok) {
					this.graph = await res.json();
				}
			} catch (e) {
				this.graph = null;
			}
		}

		/**
		 * Search with composite scoring + clustering.
		 * @param {string} query
		 * @returns {{results: Array, clusters: Array, related: Array}}
		 */
		search(query) {
			if (!this.ready || !this.fuse || !this.index) {
				return { results: [], clusters: [], related: [] };
			}

			const maxResults = this.config.settings?.maxResults || 10;
			const synonyms = this.index.synonyms || {};
			const stopWords = this.index.stop_words || [];

			// 1. Normalize
			const normalized = this.normalize(query);

			// 2. Tokenize
			const tokens = this.tokenize(normalized, stopWords);
			if (tokens.length === 0) return { results: [], clusters: [], related: [] };

			// 3. Generate n-grams
			const ngrams = generateNgrams(tokens, 3);

			// 4. Expand with synonyms + lemmas
			const expanded = this.expandSemantic(tokens, synonyms);

			// 5. Build search queries — original query first, then expanded.
			const searchQueries = [];

			// Always search the original query first (most reliable).
			searchQueries.push( normalized );
			searchQueries.push( tokens.join( ' ' ) );

			// Also try original tokens (without synonym expansion).
			const originalTokens = this.expandSemantic( tokens, {} );
			searchQueries.push( originalTokens.join( ' ' ) );

			// n-grams as additional queries.
			if ( ngrams.trigrams?.length ) searchQueries.push( ...ngrams.trigrams );
			if ( ngrams.bigrams?.length ) searchQueries.push( ...ngrams.bigrams );

			// Expanded query (with synonyms + lemmas) as final fallback.
			searchQueries.push( expanded.join( ' ' ) );

			// 6. Run Fuse.js with each query, collect unique results
			const seen = new Set();
			const rawResults = [];

			for (const sq of searchQueries) {
				const fuseResults = this.fuse.search(sq);
				for (const fr of fuseResults) {
					if (!seen.has(fr.item.id)) {
						seen.add(fr.item.id);
						rawResults.push(fr);
					}
				}
			}

			// 7. Score with composite
			const scored = rawResults.map(r => ({
				entry: r.item,
				score: this.compositeScore(r.item, normalized, tokens, expanded, 1 - (r.score || 0)),
			}));

			// 8. Filter & sort
			const filtered = scored.filter(r => r.score >= SCORE_THRESHOLD);
			filtered.sort((a, b) => b.score - a.score);
			const topResults = filtered.slice(0, maxResults);

			// 9. Cluster
			const clusters = clusterResults(topResults);

			// 10. Related content from graph
			const related = this.getRelatedContent(topResults);

			return { results: topResults, clusters, related };
		}

		/**
		 * Get related content from graph for top result.
		 * @param {Array} results
		 * @returns {Array}
		 */
		getRelatedContent(results) {
			if (!this.graph || !this.graph.edges || results.length === 0) return [];
			const topId = results[0].entry.id;
			const related = [];
			const seen = new Set();

			for (const edge of this.graph.edges) {
				let relatedId = null;
				if (edge.from === topId) relatedId = edge.to;
				else if (edge.to === topId) relatedId = edge.from;

				if (relatedId && !seen.has(relatedId)) {
					seen.add(relatedId);
					const entry = this.index.entries.find(e => e.id === relatedId);
					if (entry) {
						related.push({ entry, weight: edge.weight, type: edge.type });
					}
				}
			}

			return related
				.sort((a, b) => b.weight - a.weight)
				.slice(0, 4);
		}

		/* ── Composite score ────────────────────── */

		compositeScore(entry, normalized, tokens, expanded, fuzzyScore) {
			const title    = (entry.title    || '').toLowerCase();
			const content  = (entry.content  || '').toLowerCase();
			const keywords = (entry.keywords || []).join(' ').toLowerCase();
			const cats     = (entry.categories||[]).join(' ').toLowerCase();
			const tags     = (entry.tags     || []).join(' ').toLowerCase();
			const excerpt  = (entry.excerpt  || '').toLowerCase();
			const weight   = entry.weight || 1.0;

			// Graph score (how connected)
			const graphScore = this.calcGraphScore(entry.id);

			const score =
				(fuzzyScore        * 0.40) +
				(graphScore        * 0.20) +
				(this.exactMatchBonus(normalized, title, keywords, content) * 0.10) +
				(this.synonymBonus(content + ' ' + title, tokens, expanded) * 0.10) +
				(this.stemBonus(tokens, title, content, keywords)            * 0.05) +
				(this.coverageScore(tokens, title, content, keywords, cats, tags, excerpt) * 0.05) +
				(this.recencyBonus(entry.date || entry.modified || '')       * 0.05) +
				((weight / 10.0) * 0.05);

			return Math.min(score * (0.5 + (weight / 20.0)), 1.0);
		}

		/**
		 * Calculate graph score for an entry.
		 * @param {number} entryId
		 * @returns {number}
		 */
		calcGraphScore(entryId) {
			if (!this.graph || !this.graph.edges || this.graph.edges.length === 0) return 0;
			let count = 0;
			const total = this.graph.edges.length;
			for (const edge of this.graph.edges) {
				if (edge.from === entryId || edge.to === entryId) count++;
			}
			return total > 0 ? Math.min(count / Math.sqrt(total), 1.0) : 0;
		}

		/* ── Score components ───────────────────── */

		exactMatchBonus(query, title, keywords, content) {
			if (title.includes(query))    return 0.15;
			if (keywords.includes(query)) return 0.10;
			if (content.includes(query))  return 0.05;
			return 0;
		}

		synonymBonus(text, tokens, expanded) {
			const extra = expanded.filter(w => !tokens.includes(w));
			if (extra.length === 0) return 0;
			const hits = extra.filter(syn => text.includes(syn)).length;
			return (hits / extra.length) * 0.10;
		}

		stemBonus(tokens, title, content, keywords) {
			const space = title + ' ' + content + ' ' + keywords;
			let hits = 0;
			for (const w of tokens) {
				const forms = expandWithLemmas(w);
				if (forms.some(f => f.length >= 3 && space.includes(f))) hits++;
			}
			return tokens.length > 0 ? (hits / tokens.length) * 0.05 : 0;
		}

		coverageScore(tokens, title, content, keywords, cats, tags, excerpt) {
			const space = `${title} ${content} ${keywords} ${cats} ${tags} ${excerpt}`;
			if (tokens.length === 0) return 0;
			const matches = tokens.filter(w => space.includes(w)).length;
			return matches / tokens.length;
		}

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

		/* ── Semantic expansion ──────────────────── */

		/**
		 * Expand tokens with synonyms + lemmas.
		 * @param {string[]} tokens
		 * @param {Object} synonyms
		 * @returns {string[]}
		 */
		expandSemantic(tokens, synonyms) {
			const expanded = [...tokens];

			// Synonym expansion
			for (const token of tokens) {
				for (const [term, synList] of Object.entries(synonyms)) {
					const t = term.toLowerCase();
					const s = synList.map(x => x.toLowerCase());
					if (token === t || s.includes(token)) {
						expanded.push(...s, t);
					}
				}

				// Lemma expansion
				const lemmaForms = expandWithLemmas(token);
				expanded.push(...lemmaForms);
			}

			return [...new Set(expanded)];
		}

		/* ── Text processing ─────────────────────── */

		normalize(text) {
			return text
				.toLowerCase()
				.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
				.replace(/[¿?!¡,.;:\-'"«»()\[\]{}]/g, ' ')
				.replace(/\s+/g, ' ')
				.trim();
		}

		tokenize(text, stopWords) {
			return text.split(' ').filter(w => w.length > 1 && !stopWords.includes(w));
		}
	}

	window.ConvocaChat = ConvocaChat;
})();
