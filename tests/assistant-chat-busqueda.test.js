/**
 * Reglas de busqueda del motor del cliente (issue #5).
 *
 * Se inyecta un Fuse controlable y un indice minimo para probar, aisladas, las
 * reglas que arregla el issue #5:
 *  - dedupe por la MEJOR puntuacion de cada entrada, no la primera aparicion;
 *  - desempate a 0.90: una pagina cede ante una FAQ del mismo tema;
 *  - la coincidencia exacta de titulo manda y permite responder con una pagina;
 *  - el peso solo refuerza coincidencias fuertes (titulo/palabras clave exactos).
 *
 * Cada prueba falla si se revierte su cambio (probado en negativo).
 *
 * @jest-environment jsdom
 */

'use strict';

const RUTA_MOTOR = '../assets/js/assistant-chat.js';

/** Reinicia el entorno y construye un ConvocaChat con configuracion dada. */
function nuevoChat(ajustes = {}) {
	jest.resetModules();
	delete window.ConvocaChat;
	window.convocaAssistant = {
		indexUrl: 'https://ejemplo.org/wp-content/uploads/convoca-assistant/index.json',
		settings: Object.assign(
			{
				threshold: 0.4,
				distance: 100,
				maxResults: 10,
				priorityTypes: ['convoca_faq', 'convoca_kb'],
				priorityBoost: 1.35,
				directThreshold: 0.4,
				rankingWeights: { fuzzy: 0.45, graph: 0.1, exact: 0.15, exactTitle: 0.15 },
				weights: { title: 4, keywords: 3, categories: 2, content: 1, tags: 1 },
				weightDefaults: { convoca_faq: 2.0, convoca_kb: 1.5, post: 1.0, page: 1.0, product: 1.0 },
			},
			ajustes
		),
	};
	global.Fuse = jest.fn();
	require(RUTA_MOTOR);
	const chat = new window.ConvocaChat();
	chat.ready = true;
	chat.graph = null;
	return chat;
}

/** Entrada minima con los campos que usa el motor. */
function entrada(id, type, title, extra = {}) {
	return Object.assign(
		{
			id, type, title,
			content: '', excerpt: '', keywords: [], categories: [], tags: [],
			weight: 1, date: '', modified: '', url: '', thumbnail: '',
		},
		extra
	);
}

/** Fuse falso: devuelve resultados distintos por numero de llamada. */
function fusePorLlamada(respuestas) {
	let i = 0;
	return {
		search: () => {
			const r = respuestas[i] || [];
			i += 1;
			return r;
		},
	};
}

describe('dedupe: gana la MEJOR puntuacion de cada entrada', () => {
	test('una entrada encontrada tarde con mejor distancia adelanta a una peor', () => {
		const chat = nuevoChat();
		const x = entrada(1, 'convoca_faq', 'Alfa beta gamma');
		const y = entrada(2, 'convoca_faq', 'Delta epsilon zeta');
		chat.index = { entries: [x, y], synonyms: {}, stop_words: [] };
		// En la PRIMERA formulacion X llega con mala distancia (0.9); en la segunda, con
		// distancia 0.0. Guardar solo la primera aparicion puntuaria a X con 0.9.
		chat.fuse = fusePorLlamada([
			[{ item: y, score: 0.2 }, { item: x, score: 0.9 }],
			[{ item: x, score: 0.0 }],
		]);

		const out = chat.search('gamma alfa');
		expect(out.results[0].entry.id).toBe(1);
	});
});

describe('desempate a 0.90 entre pagina y FAQ', () => {
	test('la FAQ gana a la pagina cuando ambas coinciden en el titulo', () => {
		const chat = nuevoChat();
		const pagina = entrada(10, 'page', 'Manual de uso del Centro Social');
		const faq = entrada(20, 'convoca_faq', 'Como consultar el manual de uso del centro');
		chat.index = { entries: [pagina, faq], synonyms: {}, stop_words: [] };
		// La pagina entra PRIMERO: es lo que antes decidia el empate.
		chat.fuse = fusePorLlamada([[{ item: pagina, score: 0.1 }, { item: faq, score: 0.1 }]]);

		const out = chat.search('manual de uso del centro');
		expect(out.results.map((r) => r.entry.id)).toEqual([20, 10]);
	});

	test('la coincidencia exacta de titulo manda: una pagina exacta sigue ganando', () => {
		const chat = nuevoChat();
		const pagina = entrada(10, 'page', 'Espacios');
		const faq = entrada(20, 'convoca_faq', 'Que espacios tiene el centro');
		chat.index = { entries: [pagina, faq], synonyms: {}, stop_words: [] };
		chat.fuse = fusePorLlamada([[{ item: faq, score: 0.1 }, { item: pagina, score: 0.1 }]]);

		const out = chat.search('espacios');
		expect(out.results[0].entry.id).toBe(10);
	});
});

describe('el peso solo refuerza coincidencias fuertes', () => {
	test('en coincidencia floja, el peso no cambia la puntuacion', () => {
		const chat = nuevoChat();
		const pesada = entrada(1, 'convoca_faq', 'Taller de cocina', { weight: 8, keywords: ['cocina musical'] });
		const normal = entrada(2, 'convoca_faq', 'Taller de cocina', { weight: 2, keywords: ['cocina musical'] });

		// Consulta con las mismas palabras en otro orden: NO esta contenida ni en el titulo
		// ni en las palabras clave, asi que la coincidencia es floja.
		const norm = chat.normalize('musical cocina');
		const tokens = chat.tokenize(norm, []);
		const expanded = chat.expandSemantic(tokens, {});

		const sPesada = chat.compositeScore(pesada, norm, tokens, expanded, 0.5);
		const sNormal = chat.compositeScore(normal, norm, tokens, expanded, 0.5);

		expect(sPesada).toBeCloseTo(sNormal, 10);
	});

	test('en coincidencia fuerte (palabras clave exactas), el peso si refuerza', () => {
		const chat = nuevoChat();
		const pesada = entrada(1, 'convoca_faq', 'Taller de cocina', { weight: 8, keywords: ['cocina musical'] });
		const normal = entrada(2, 'convoca_faq', 'Taller de cocina', { weight: 2, keywords: ['cocina musical'] });

		const norm = chat.normalize('cocina musical');
		const tokens = chat.tokenize(norm, []);
		const expanded = chat.expandSemantic(tokens, {});

		const sPesada = chat.compositeScore(pesada, norm, tokens, expanded, 0.5);
		const sNormal = chat.compositeScore(normal, norm, tokens, expanded, 0.5);

		expect(sPesada).toBeGreaterThan(sNormal);
	});
});
