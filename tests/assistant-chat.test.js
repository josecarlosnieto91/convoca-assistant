/**
 * @jest-environment jsdom
 */

// Mock Fuse.js
global.Fuse = jest.fn().mockImplementation(() => ({
	search: jest.fn().mockReturnValue([]),
}));

// Load session
require('../assets/js/assistant-session.js');

describe('SessionMemory', () => {
	beforeEach(() => {
		localStorage.clear();
	});

	test('SessionMemory class exists', () => {
		const session = new window.ConvocaSession();
		expect(session).toBeDefined();
		expect(session.data.sessionId).toBeDefined();
	});

	test('addQuery records query and extracts topics', () => {
		const session = new window.ConvocaSession();
		session.addQuery('cómo renovar la licencia', [42, 15]);
		expect(session.data.queries.length).toBe(1);
		expect(session.data.queries[0].text).toBe('cómo renovar la licencia');
		expect(session.data.topics).toContain('renovar');
		expect(session.data.topics).toContain('licencia');
	});

	test('markClicked adds to clicked array', () => {
		const session = new window.ConvocaSession();
		session.markClicked(42);
		expect(session.data.clicked).toContain(42);
	});

	test('getSeenIds includes clicked and result IDs', () => {
		const session = new window.ConvocaSession();
		session.addQuery('test', [1, 2, 3]);
		session.markClicked(1);
		const seen = session.getSeenIds();
		expect(seen).toContain(1);
		expect(seen).toContain(2);
		expect(seen).toContain(3);
	});

	test('clear resets session', () => {
		const session = new window.ConvocaSession();
		session.addQuery('test', [1]);
		session.clear();
		expect(session.data.queries.length).toBe(0);
		expect(session.data.topics.length).toBe(0);
	});
});

describe('ConvocaChat n-grams and lemmatization', () => {
	beforeEach(() => {
		delete window.ConvocaChat;
	});

	test('normalize removes accents', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		expect(chat.normalize('¿Cómo estás?')).toBe('como estas');
	});

	test('tokenize removes stop words', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const result = chat.tokenize('el gato en la casa', ['el', 'la', 'en']);
		expect(result).toEqual(['gato', 'casa']);
	});

	test('expandSemantic adds lemmas', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		const result = chat.expandSemantic(['inscripción'], {});
		expect(result).toContain('inscripción');
		expect(result).toContain('inscribir');
	});

	test('calcGraphScore with no graph', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		chat.graph = null;
		expect(chat.calcGraphScore(1)).toBe(0);
	});

	test('calcGraphScore with edges', () => {
		require('../assets/js/assistant-chat.js');
		const chat = new window.ConvocaChat();
		chat.graph = {
			edges: [
				{ from: 1, to: 2, type: 'same', weight: 0.3 },
				{ from: 2, to: 3, type: 'same', weight: 0.4 },
			]
		};
		const score1 = chat.calcGraphScore(1);
		const score3 = chat.calcGraphScore(3);
		expect(score1).toBeGreaterThan(0);
		expect(score3).toBeGreaterThan(0);
	});
});
