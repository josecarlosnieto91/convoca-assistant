/**
 * Arnés de calidad del buscador — MOTOR DEL CLIENTE (Fuse.js).
 *
 * Mide el motor que usa el visitante: llama a ConvocaChat.search(), que es el mismo
 * camino que ejecuta el widget (assistant-widget.js:249). No reimplementa el pipeline.
 *
 * Corre SIN red y SIN WordPress: el fetch se simula y todo sale de los fixtures congelados.
 *
 * Spec: docs/buscador-evaluacion-spec.md
 *
 * @jest-environment jsdom
 */

const fs = require('fs');
const path = require('path');

const FIXTURES = path.join(__dirname, 'fixtures');
const FICHERO_INDICE = path.join(FIXTURES, 'index-lugg-20260927.json');
const FICHERO_GRAFO = path.join(FIXTURES, 'graph-lugg-20260927.json');
const FICHERO_CONSULTAS = path.join(FIXTURES, 'eval-queries.json');
const FICHERO_SALIDA = path.join(FIXTURES, 'search-quality-client.json');

// ── Configuración REAL del sitio de Lugg (convoca_assistant_settings, 27/09/2026) ──
// Los mismos valores que Widget.php vuelca a window.convocaAssistant.
const AJUSTES_DEL_SITIO = {
	threshold: 0.4,
	distance: 100,
	maxResults: 10,
	priorityTypes: ['convoca_faq', 'convoca_kb'],
	priorityBoost: 1.35,
	directThreshold: 0.55,
	rankingWeights: { fuzzy: 0.45, graph: 0.1, exact: 0.15, exactTitle: 0.15 },
	weights: { title: 4, keywords: 3, categories: 2, content: 1, tags: 1 },
};

// ── Métricas (mismas fórmulas que el arnés del servidor, ver la spec §3) ──

function posicionDeAcierto(devueltos, esperados) {
	for (let i = 0; i < devueltos.length; i++) {
		if (esperados.includes(String(devueltos[i]))) return i + 1;
	}
	return 0; // sin acierto
}

function ndcg5(devueltos, esperados) {
	const dcg = (pos) => (pos > 0 && pos <= 5 ? 1 / Math.log2(pos + 1) : 0);
	const real = dcg(posicionDeAcierto(devueltos.slice(0, 5), esperados));
	const ideal = esperados.length > 0 ? dcg(1) : 0; // 1 es el mejor caso posible
	return ideal > 0 ? real / ideal : 0;
}

function metricasDe(consultas, resultados) {
	const puntuables = consultas.filter((c) => c.esperados.length > 0);
	const filas = [];
	let recall1 = 0, recall3 = 0, mrr = 0, ndcg = 0;

	// Las consultas sin respuesta esperada también se diagnostican (cobertura), pero no puntúan.
	for (const c of consultas) {
		const devueltos = resultados[c.query] || [];
		if (c.esperados.length === 0) {
			filas.push({ query: c.query, esperados: [], devueltos: devueltos.slice(0, 5), posicion: null, cobertura: devueltos.length > 0 });
			continue;
		}
		const pos = posicionDeAcierto(devueltos, c.esperados);
		if (pos === 1) recall1++;
		if (pos > 0 && pos <= 3) recall3++;
		if (pos > 0 && pos <= 10) mrr += 1 / pos;
		ndcg += ndcg5(devueltos, c.esperados);
		filas.push({ query: c.query, esperados: c.esperados, devueltos: devueltos.slice(0, 5), posicion: pos, cobertura: true });
	}

	const n = puntuables.length || 1;
	return {
		puntuables: puntuables.length,
		recall1: recall1 / n,
		recall3: recall3 / n,
		mrr10: mrr / n,
		ndcg5: ndcg / n,
		filas,
	};
}

// ── El arnés ──

describe('Calidad del buscador — motor del cliente', () => {
	let chat;
	let consultas;

	beforeAll(async () => {
		const indice = JSON.parse(fs.readFileSync(FICHERO_INDICE, 'utf8'));
		const grafo = JSON.parse(fs.readFileSync(FICHERO_GRAFO, 'utf8'));
		consultas = JSON.parse(fs.readFileSync(FICHERO_CONSULTAS, 'utf8')).consultas;

		// Fuse real, no un mock: el MISMO bundle que sirve el sitio (UMD, Apache 2.0).
		const moduloFuse = require('../assets/js/fuse.bundle.js');
		global.Fuse = moduloFuse.default || moduloFuse;

		// El fetch del init() se simula: así el arnés es determinista y corre sin red.
		global.fetch = jest.fn((url) => {
			if (String(url).endsWith('graph.json')) {
				return Promise.resolve({ ok: true, json: () => Promise.resolve(grafo) });
			}
			return Promise.resolve({ ok: true, json: () => Promise.resolve(indice) });
		});

		window.convocaAssistant = {
			indexUrl: 'https://lugg.biodevas.org/wp-content/uploads/convoca-assistant/index.json',
			settings: AJUSTES_DEL_SITIO,
		};

		// El chat se carga DESPUÉS de configurar la ventana, como en el sitio.
		require('../assets/js/assistant-chat.js');
		chat = new window.ConvocaChat();
		const listo = await chat.init();
		expect(listo).toBe(true);
	});

	test('el motor arranca con el corpus congelado', () => {
		expect(chat.ready).toBe(true);
		expect(chat.index.entries.length).toBe(336);
		expect(chat.fuse).toBeTruthy();
	});

	test('mide el set completo y deja cada consulta diagnosticada', () => {
		const resultados = {};
		for (const c of consultas) {
			const salida = chat.search(c.query);
			resultados[c.query] = (salida.results || []).map((r) => String(r.entry.id));
		}

		const m = metricasDe(consultas, resultados);

		// Invariantes del arnés: ninguna consulta sin diagnóstico y métricas en rango.
		expect(Object.keys(resultados).length).toBe(consultas.length);
		for (const k of ['recall1', 'recall3', 'mrr10', 'ndcg5']) {
			expect(m[k]).toBeGreaterThanOrEqual(0);
			expect(m[k]).toBeLessThanOrEqual(1);
		}
		for (const f of m.filas) {
			if (f.esperados.length === 0) {
				expect(f.posicion).toBeNull(); // cobertura: no puntúa
			} else {
				expect(f.posicion === 0 || f.posicion >= 1).toBe(true);
			}
		}

		// La tabla, para que quede en el registro del CI.
		const ancho = Math.max(...m.filas.map((f) => f.query.length));
		console.log('\n=== CLIENTE (Fuse.js) ===');
		for (const f of m.filas) {
			const estado = null === f.posicion
				? `cobertura: ${f.cobertura ? 'devuelve algo, sin respuesta esperada' : 'NO devuelve nada'}`
				: (f.posicion === 0 ? 'FALLA' : `posición ${f.posicion}`);
			console.log(
				`  ${f.query.padEnd(ancho)}  esperado [${f.esperados.join(', ')}]  ·  ${estado}` +
					`  ·  devuelto: ${f.devueltos.join(', ') || '(nada)'}`
			);
		}
		const sinRespuesta = consultas.filter((c) => c.esperados.length === 0);
		console.log(
			`\n  Recall@1 ${(m.recall1 * 100).toFixed(1)}% · Recall@3 ${(m.recall3 * 100).toFixed(1)}%` +
				` · MRR@10 ${m.mrr10.toFixed(3)} · nDCG@5 ${m.ndcg5.toFixed(3)}` +
				`  ·  ${m.puntuables} consultas puntuables, ${sinRespuesta.length} sin respuesta esperada (cobertura)`
		);

		fs.writeFileSync(
			FICHERO_SALIDA,
			JSON.stringify(
				{
					motor: 'cliente',
					corpus: 'index-lugg-20260927.json',
					ajustes: AJUSTES_DEL_SITIO,
					resumen: { consultas: m.puntuables, recall1: m.recall1, recall3: m.recall3, mrr10: m.mrr10, ndcg5: m.ndcg5 },
					filas: m.filas,
				},
				null,
				2
			) + '\n'
		);
	});
});
