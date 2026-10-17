/**
 * Medición del buscador con el SET DE PRUEBAS DE LA DEMO.
 *
 * Necesita el corpus exportado (no se versiona: contiene contenido del sitio). Si no se le dan las
 * rutas, el test se SALTA y no molesta al CI:
 *
 *   CONVOCA_BATERIA_INDICE=<index.json> CONVOCA_BATERIA_GRAFO=<graph.json> \
 *   CONVOCA_BATERIA_AJUSTES=<ajustes.json> cd tests && npx jest bateria-demo
 *
 * Usa el motor del CLIENTE (Fuse.js) sobre el índice real, que es el camino que ve el visitante.
 *
 * @jest-environment jsdom
 */

const fs = require('fs');
const path = require('path');

const INDICE = process.env.CONVOCA_BATERIA_INDICE || '';
const GRAFO = process.env.CONVOCA_BATERIA_GRAFO || '';
const AJUSTES_FICHERO = process.env.CONVOCA_BATERIA_AJUSTES || '';
const CONSULTAS_FICHERO = process.env.CONVOCA_BATERIA_CONSULTAS
  || path.join(__dirname, 'fixtures', 'eval-queries-demo.json');

const disponible = INDICE !== '' && GRAFO !== '' && AJUSTES_FICHERO !== '' && fs.existsSync(INDICE);
const suite = disponible ? describe : describe.skip;

suite('Batería del buscador sobre la demo', () => {
  let chat, consultas, ajustes;

  beforeAll(async () => {
    const indice = JSON.parse(fs.readFileSync(INDICE, 'utf8'));
    const grafo = JSON.parse(fs.readFileSync(GRAFO, 'utf8'));
    const aj = JSON.parse(fs.readFileSync(AJUSTES_FICHERO, 'utf8'));
    consultas = JSON.parse(fs.readFileSync(CONSULTAS_FICHERO, 'utf8')).consultas;

        ajustes = {
      threshold: aj.search_fuse_threshold ?? 0.4,
      distance: aj.search_fuse_distance ?? 100,
      maxResults: aj.search_max_results ?? 10,
      priorityTypes: aj.priority_types ?? ['convoca_faq', 'convoca_kb'],
      priorityBoost: aj.priority_boost ?? 1.35,
      directThreshold: aj.direct_threshold ?? 0.4,
      rankingWeights: {
        fuzzy: aj.weights_fuzzy ?? 0.45, graph: aj.weights_graph ?? 0.1,
        exact: aj.weights_exact ?? 0.15, exactTitle: aj.weights_exact_title ?? 0.15,
      },
      weights: { title: 4, keywords: 3, categories: 2, content: 1, tags: 1 },
      weightDefaults: {
        convoca_faq: aj.weight_convoca_faq ?? 2, convoca_kb: aj.weight_convoca_kb ?? 1.5,
        post: aj.weight_post ?? 1, page: aj.weight_page ?? 1, product: 1,
      },
    };
    const moduloFuse = require('../assets/js/fuse.bundle.js');
    global.Fuse = moduloFuse.default || moduloFuse;
    global.fetch = jest.fn((url) => Promise.resolve({
      ok: true, json: () => Promise.resolve(String(url).endsWith('graph.json') ? grafo : indice) }));
    window.convocaAssistant = {
      indexUrl: 'https://demo.getconvoca.app/wp-content/uploads/convoca-assistant/index.json',
      settings: ajustes,
    };
    require('../assets/js/assistant-chat.js');
    chat = new window.ConvocaChat();
    expect(await chat.init()).toBe(true);
  });

  test('mide el set completo y deja cada consulta diagnosticada', () => {
    const filas = [];
    let r1 = 0, r3 = 0, mrr = 0, ndcg = 0, puntuables = 0, directas = 0, empates = 0, ganaFaq = 0, ganaPagina = 0;
    let fallosEmpate = [];

    for (const c of consultas) {
      const res = (chat.search(c.query).results || []);
      const ids = res.map((x) => String(x.entry.id));
      const pos = c.esperados.length ? (ids.findIndex((i) => c.esperados.includes(i)) + 1) : null;
      const top = res[0] ? res[0].score : 0;
      if (res[0] && top >= (ajustes.directThreshold ?? 0.4)) directas++;
      if (c.esperados.length) {
        puntuables++;
        if (pos === 1) r1++;
        if (pos >= 1 && pos <= 3) r3++;
        if (pos >= 1 && pos <= 10) mrr += 1 / pos;
        ndcg += (pos >= 1 && pos <= 5) ? 1 / Math.log2(pos + 1) : 0;
      }
      // Empate: el GRUPO de puntuacion mas alta >=0,90; quien va primero en ese grupo.
      const max = res.length ? res[0].score : 0;
      if (max >= 0.9) {
        const grupo = res.filter((x) => Math.abs(x.score - max) < 1e-9);
        const hayPagina = grupo.some((x) => x.entry.type === 'page');
        const hayPri = grupo.some((x) => ajustes.priorityTypes.includes(x.entry.type));
        if (hayPagina && hayPri) {
          empates++;
          if (grupo[0].entry.type === 'page') {
            ganaPagina++;
            if (c.caso === 'empate_pagina_faq') fallosEmpate.push(c.query);
          } else { ganaFaq++; }
        }
      }
      const segundo = res[1] ? Number(res[1].score.toFixed(4)) : null;
      filas.push({ query: c.query, caso: c.caso, esperados: c.esperados, devueltos: ids.slice(0, 3),
                   top: Number(top.toFixed(4)), segundo, margen: null === segundo ? null : Number((top - segundo).toFixed(4)),
                   posicion: pos, directa: res[0] ? top >= (ajustes.directThreshold ?? 0.4) : false });
    }

    // ── Diagnóstico para assistant#15: ¿la entrada que responde comparte TEMA con la consulta? ──
    // Se mide, no se aplica: cuántos aciertos callaría el criterio de exigir coincidencia en título o
    // palabras clave, y cuántos ruidos callaría. Sin estos números, decidir era opinar.
    const stop = new Set((chat.index && chat.index.stop_words) || []);
    const significativos = (q) => chat.normalize(q).split(' ').filter((w) => w.length >= 4 && !stop.has(w));
    const evidencia = (q, f) => {
      const ts = significativos(q);
      const idx = (chat.index && chat.index.entries) || [];
      const entrada = idx.find((e) => String(e.id) === String(f.devueltos[0]));
      if (!ts.length || !entrada) return null;
      const heno = chat.normalize([entrada.title || '', (entrada.keywords || []).join(' ')].join(' '));
      return ts.some((tk) => heno.includes(tk));
    };
    let posSinEvidencia = [], negConEvidencia = [], negSinEvidencia = [], negResponden = [];
    for (const f of filas) {
      f.evidencia = evidencia(f.query, f);
      if (f.esperados.length) { if (false === f.evidencia) posSinEvidencia.push(f.query); }
      else {
        if (f.directa) negResponden.push(f.query);
        if (true === f.evidencia) negConEvidencia.push(f.query); else negSinEvidencia.push(f.query);
      }
    }
    console.log('\n  === assistant#15: el criterio de titulo/claves, MEDIDO (no aplicado) ===');
    console.log('  negativas que responden hoy: ' + negResponden.length + '/' + (filas.length - puntuables));
    console.log('  el criterio callaria ' + negSinEvidencia.length + ' de esas negativas, y seguirian respondiendo ' + negConEvidencia.length);
    console.log('  coste: callaria ' + posSinEvidencia.length + ' de ' + puntuables + ' aciertos' + (posSinEvidencia.length ? ' → ' + posSinEvidencia.join(' | ') : ''));
    for (const f of filas.filter((x) => !x.esperados.length)) {
      console.log('     negativa: ' + (f.directa ? 'RESPONDE ' : 'calla    ') + ' evidencia=' + String(f.evidencia) +
        '  ' + f.query.slice(0, 42) + '  → [' + f.devueltos.slice(0, 2).join(',') + ']');
    }

    const n = puntuables || 1;
    const ancho = Math.max(...filas.map((f) => f.query.length));
    console.log('\n=== BATERÍA DEMO (motor del cliente) ===');
    for (const f of filas) {
      const estado = null === f.posicion
        ? (f.directa ? 'SIN RESPUESTA ESPERADA y responde' : 'sin respuesta (correcto)')
        : (f.posicion === 0 ? 'FALLA' : 'pos ' + f.posicion);
      console.log('  ' + (f.caso || '').padEnd(18) + ' ' + f.query.padEnd(ancho) + '  ' + f.top.toFixed(4) +
        ' gap ' + String(null === f.margen ? '—' : f.margen.toFixed(4)).padStart(6) +
        '  ' + estado + '   esperado [' + f.esperados.join(',') + ']  devuelto [' + f.devueltos.join(',') + ']');
    }
    console.log('\n  RESUMEN            Recall@1   Recall@3    MRR@10   nDCG@5');
    console.log('  cliente           ' + ((r1 / n) * 100).toFixed(1).padStart(8) + '%' + ((r3 / n) * 100).toFixed(1).padStart(10) + '%' +
      (mrr / n).toFixed(3).padStart(10) + (ndcg / n).toFixed(3).padStart(9));
    console.log('  consultas puntuables ' + puntuables + ' · sin respuesta esperada ' + (consultas.length - puntuables) +
      ' · responden directo ' + directas + '/' + consultas.length);
    console.log('  empates >=0,90 pagina/FAQ: ' + empates + ' · gana la FAQ ' + ganaFaq +
      ' · precision ' + (empates ? ((ganaFaq / empates) * 100).toFixed(1) : 'n/a') + '%');
    if (process.env.CONVOCA_BATERIA_SALIDA) {
      fs.writeFileSync(process.env.CONVOCA_BATERIA_SALIDA, JSON.stringify(
        { resumen: { puntuables, recall1: r1 / n, recall3: r3 / n, mrr10: mrr / n, ndcg5: ndcg / n, directas, empates, ganaFaq }, filas }, null, 1));
    }
    // assistant#14: la FAQ del aparcabicis tiene que salir con la consulta coloquial. Antes respondía
    // la del centro social porque «bici» no se buscaba nunca como token suelto (sólo la frase entera,
    // los bigramas y los trigramas), y el Fuse no casa un patrón de 19 caracteres con una palabra de 4.
    const bici = filas.find((f) => f.query.includes('bici'));
    expect(bici).toBeDefined();
    expect(String(bici.devueltos[0])).toBe('3019');

    expect(filas.length).toBe(consultas.length);
    if (fallosEmpate.length) { console.log('  AVISO: empate a 0,90 resuelto a favor de la PAGINA en: ' + fallosEmpate.join(', ')); }
    expect(fallosEmpate.length).toBe(0);
  });
});
