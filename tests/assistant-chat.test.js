/**
 * @jest-environment jsdom
 */

// Mock Fuse.js — the actual bundled file creates a global Fuse
global.Fuse = jest.fn().mockImplementation(() => ({
	search: jest.fn().mockReturnValue([]),
}));

// Import the module
const fs = require('fs');
const path = require('path');

describe('ConvocaChat Engine', () => {
	beforeEach(() => {
		document.body.innerHTML = '';
		// Module is loaded once via require; don't delete window.ConvocaChat
	});

	test('ConvocaChat class exists', () => {
		require('../assets/js/assistant-chat.js');
		expect(window.ConvocaChat).toBeDefined();
	});

	test('normalize removes accents and punctuation', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const result = chat.normalize('¿Cómo estás? ¡Bien!');
		expect(result).toBe('como estas bien');
	});

	test('normalize lowercase', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		expect(chat.normalize('HOLA MUNDO')).toBe('hola mundo');
	});

	test('tokenize removes stop words', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const stopWords = ['el', 'la', 'de', 'en', 'y'];
		const result = chat.tokenize('el gato en la casa', stopWords);
		expect(result).not.toContain('el');
		expect(result).not.toContain('la');
		expect(result).not.toContain('en');
		expect(result).toContain('gato');
		expect(result).toContain('casa');
	});

	test('expandSynonyms adds related terms', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const synonyms = {
			'ordenador': ['computadora', 'pc'],
			'coche': ['auto', 'automóvil'],
		};
		const result = chat.expandSynonyms(['ordenador'], synonyms);
		expect(result).toContain('computadora');
		expect(result).toContain('pc');
		expect(result).toContain('ordenador');
	});

	test('recencyBonus returns higher for recent dates', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();

		const recent = chat.recencyBonus(new Date().toISOString());
		const old = chat.recencyBonus('2020-01-01');

		expect(recent).toBeGreaterThan(old);
		expect(recent).toBe(0.05);
		expect(old).toBe(0);
	});

	test('exactMatchBonus for title match', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const bonus = chat.exactMatchBonus(
			'como registrarse',
			'como registrarse en la asociacion',
			'',
			''
		);
		expect(bonus).toBe(0.15);
	});

	test('compositeScore returns between 0 and 1', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();

		const score = chat.compositeScore(
			{
				title: 'Test title',
				content: 'Test content',
				keywords: ['test'],
				categories: [],
				tags: [],
				excerpt: 'Test',
				weight: 1.0,
				date: new Date().toISOString(),
			},
			'test',
			['test'],
			['test'],
			0.8
		);

		expect(score).toBeGreaterThanOrEqual(0);
		expect(score).toBeLessThanOrEqual(1);
	});
});
