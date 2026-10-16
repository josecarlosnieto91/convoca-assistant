# Set de pruebas del buscador sobre la demo

Qué es: un **corpus QA creado en demo.getconvoca.app** (entradas marcadas con el meta `_qa_bateria`,
ids 3009-3023) más el contenido que el sitio ya tenía, y un **set de 18 consultas etiquetadas** con la
entrada que debe responder cada una. Existe porque las 34 consultas del set antiguo están etiquetadas
contra el corpus de ejemplo: sirven para el corpus de ejemplo y para nada más.

- Set: `tests/fixtures/eval-queries-demo.json` (contenido sintético: se versiona).
- Medidor: `tests/bateria-demo.test.js` (motor del cliente, Fuse.js real). **Se salta solo** si no se le
  da un corpus, así que no afecta al CI.
- Corpus: **no se versiona** (contiene contenido del sitio). Se reexporta:
  `scp andromeda:/var/www/demo.getconvoca.app/public/wp-content/uploads/convoca-assistant/index.json .`
  Copia de trabajo (con su md5 y el grafo): `~/backups-convoca/bateria-buscador-demo/`.

## Cómo se corre

```
CONVOCA_BATERIA_INDICE=<index.json> CONVOCA_BATERIA_GRAFO=<graph.json> \
CONVOCA_BATERIA_AJUSTES=<ajustes.json> cd tests && npx jest bateria-demo
```

Corpus de la medición del 16/10/2026: 69 entradas (15 de QA), md5 `a6f756783d7857f1228fc44bfe33364a`.

## Los casos que cubre (y para qué)

| caso | qué comprueba |
|---|---|
| `faq_exacta` | título exacto de una FAQ |
| **`empate_pagina_faq`** | **empate a 0,90 entre una página y una FAQ con el mismo título: debe ganar la FAQ** |
| `pagina_titulo_exacto` | título exacto de una **página**: la página debe ganar (la coincidencia exacta manda) |
| `keywords` | palabras que solo viven en las palabras clave de la entrada |
| `coloquial` | la misma pregunta dicha de otra manera |
| `kb` | entrada de la base de conocimiento |
| `peso` | entrada con peso 3: el peso no debe desempatar en coincidencia floja |
| `distractor` | entradas cercanas que NO son la respuesta |
| `ambiguo` | consulta que admite dos entradas: vale cualquiera |
| `sin_respuesta` | no existe en el corpus: el buscador no debe responder |

## La medición (16/10/2026)

| | Recall@1 | Recall@3 | MRR@10 | nDCG@5 | empates ≥0,90 | precisión del empate |
|---|---|---|---|---|---|---|
| base `92833fd` (sin el arreglo) | 88,2% | 94,1% | 0,902 | 0,912 | 2 | **50,0%** |
| rama `c53aeea` = `main` | **94,1%** | 94,1% | **0,941** | **0,941** | 2 | **100,0%** |

El test **falla** con el código de la base (la FAQ pierde el empate) y **pasa** con el de `main`: es el
control negativo del arreglo, ahora sobre datos y consultas verificables. El delta del arreglo en este set:
**+5,9 puntos de Recall@1, +0,039 de MRR y +0,029 de nDCG@5**.

## Dos defectos que el set destapa y que NO son del arreglo

Fallan igual en base, rama y `main` (los ids son de la demo):

1. **`¿dónde dejo la bici?`** (0,4038) devuelve la FAQ del centro social y **la FAQ del aparcabicis
   (3019, con `bici` entre sus palabras clave) no aparece siquiera en los resultados**. No es orden: es
   recuperación.
2. **`¿Cuánto cuesta el alquiler de la furgoneta?`** —que no existe en el corpus— **responde** con las dos
   FAQs de precios (0,4150): el umbral de 0,4 deja pasar ruido cuando la consulta no tiene respuesta.

Los dos quedan como issue en el repositorio del asistente, con esta medición como evidencia.
