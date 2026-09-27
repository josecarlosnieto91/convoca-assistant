# Buscador del asistente — método de medición y línea base

Spec de la Fase 0 del goal `convoca-buscador-evaluacion-2026-09`. **Este documento va antes del código**
(regla 1 del goal). Su función es que cualquiera pueda repetir la medición y entender por qué los números
son los que son.

- Plugin: `convoca-assistant` (**0.2.8**), repo `~/repos/convoca-assistant`.
- Corpus congelado: `tests/fixtures/index-ejemplo-20260927.json`
- Set de consultas: `tests/fixtures/eval-queries.json`
- Fecha de la medición de referencia: 27/09/2026.

---

## 1. Para qué sirve esto

Hoy el buscador ordena con una fórmula compuesta hecha a mano y **no hay forma de saber si ordena bien**.
Sin un número de partida, cualquier cambio de pesos es una opinión. La Fase 0 produce ese número: un
conjunto de consultas con su respuesta esperada y cuatro métricas, medidas **en los dos motores** que el
visitante puede acabar usando.

Lo que **no** hace esta fase: tocar el ranking. El comportamiento actual se queda exactamente como está.

---

## 2. Lo que hay hoy (medido, no supuesto)

Hay **un solo índice y dos motores**:

| | Cliente (el que usa el visitante) | Servidor (respaldo) |
|---|---|---|
| Motor | Fuse.js v7.1.0 (`assets/js/fuse.bundle.js`) | `Searcher::calculate_score()` |
| Punto de entrada | `ConvocaChat.search(query)` en `assets/js/assistant-chat.js` → `{results, clusters, related}`. El widget lo llama en `assistant-widget.js:249`. | `Searcher::search( string $query, int $max_results = 10, float $threshold = 0.10 )` |
| Índice | el mismo `index.json` | el mismo `index.json` |
| Umbral | `search_fuse_threshold` = 0,4 | `search_threshold` = 0,1 |
| Distancia | `search_fuse_distance` = 100 | — |
| Claves y pesos | `title` 4 · `keywords` 3 · `categories` 2 · `content` 1 · `tags` 1 | título (Levenshtein) 0,45 · grafo 0,10 · exacto 0,15 · sinónimos 0,10 · stems 0,05 · cobertura 0,05 · recencia 0,05 · `weight` |
| Extra | varias sub-consultas a Fuse con unión de resultados; atajo `direct_threshold` = 0,55; tipos prioritarios (`convoca_faq`, `convoca_kb`) con `priority_boost` = 1,35 | los mismos ajustes de prioridad y tope |

**Consecuencia que gobierna todo el diseño:** medir el cliente **no** es llamar a Fuse a pelo. Su orden
final es Fuse **más** esas reglas propias. El arnés del cliente llama a `ConvocaChat.search()`, que es
literalmente el mismo código que ejecuta el widget, en vez de reimplementar el pipeline.

Y medir solo el servidor sería medir el respaldo, que es justo lo que el visitante **no** usa.

Dato ya observado que justifica medir los dos: la consulta «¿Qué servicios ofrecéis?» figura en el registro
con `response_found = 0` (sin respuesta) mientras el servidor hoy devuelve 10 resultados. Los dos motores
discrepan; el arnés tiene que poder verlo.

---

## 3. Qué se mide y cómo

Relevancia **binaria**: una entrada es correcta si su `id` está en `esperados` de la consulta. Sin grados:
etiquetar relevancia graduada exige un juicio que hoy no existe y que sería ruido.

Con `R` = resultados devueltos en orden, `E` = conjunto de ids esperados:

| Métrica | Definición | Qué castiga |
|---|---|---|
| **Recall@1** | 1 si el primer resultado está en `E`; media sobre todas las consultas | no acertar a la primera |
| **Recall@3** | 1 si algún resultado de los 3 primeros está en `E` | fallar el top-3 |
| **MRR@10** | media de `1/posición` del primer acierto dentro de los 10 primeros; 0 si no hay acierto | acertar tarde |
| **nDCG@5** | ganancia 1 por acierto, descuento `1/log2(posición+1)`; normalizada por el ideal de 5 posiciones | el orden dentro del top-5 |

Reglas de aplicación:

- Se usa solo el **top-10** (el tope real del producto, `search_max_results` = 10).
- Empates de puntuación: se conserva el orden devuelto por el motor. No se reordena a mano.
- **Consultas sin respuesta esperada** (`esperados: []`, hoy 1: «¿Qué servicios ofrecéis?») **no entran
  en las medias**: se cuentan aparte, como *cobertura*. Una consulta sin respuesta correcta conocida no
  puede puntuar; lo que mide es si el motor devuelve algo plausible o nada.
- Una consulta con 2-3 respuestas válidas acierta si aparece **cualquiera** de ellas: es lo que espera la
  persona que pregunta.
- Las métricas se calculan de la misma forma en los dos motores, con el mismo set y el mismo índice.

---

## 4. Corpus congelado

- Fichero: `tests/fixtures/index-ejemplo-20260927.json` — **336 entradas** (post 181 · page 21 ·
  `convoca_faq` 134), 863.241 bytes, **md5 `a20b40d56a5c6a7212da85724233c114`**.
- Origen: `https://ejemplo.org/wp-content/uploads/convoca-assistant/index.json`
  (`/datos/www/ejemplo.org/wp-content/uploads/convoca-assistant/index.json`), generado el
  27/09/2026 a las 06:25 UTC, esquema 1.
- Es **byte a byte** el fichero del sitio: el md5 del fixture es el mismo que md5 del fichero servido.
  Por eso la procedencia va en `index-ejemplo-20260927.meta.json` y no dentro del JSON: **JSON no admite
  comentarios** y meterlos cambiaría el md5, que es justo lo que queremos poder comprobar.
- El índice trae dentro `synonyms`, `stop_words` y `config` (umbrales y pesos de Fuse). Por eso el arnés
  corre **sin WordPress y sin red**: todo lo que necesita está en el fichero.
- **El fixture no se toca nunca.** Si Ejemplo regenera su índice, este fichero sigue igual: es la referencia
  contra la que se comparan las mediciones. Refrescar el corpus = crear un fichero nuevo con la fecha del
  día y anotar su md5 y su recuento aquí.
- Para que el motor de servidor no pierda su componente de grafo, se congela también el grafo del mismo
  día: `tests/fixtures/graph-ejemplo-20260927.json` — **4.605 aristas**, 280.856 bytes, md5
  `0c0f9f4c0668ca24d61c868b2ec212fd`, con los mismos `version` y `generated` que el índice. El arnés lo
  carga en `CONVOCA_ASSISTANT_INDEX_DIR` junto al índice; sin él, el componente de grafo (0,10 de peso en
  el servidor) valdría cero y la línea base no sería la del sitio.

---

## 5. Set de consultas

`tests/fixtures/eval-queries.json`. Esquema por consulta (el del goal) más dos campos de procedencia:

```json
{
  "query": "darme de baja",
  "frecuencia": null,
  "esperados": ["11923"],
  "nota": "…",
  "origen": "propuesta | titulo_faq | hueco_medido | manual_jc",
  "revisar": true
}
```

**No hay consultas de personas todavía, y hay que decirlo claro.** Se rastrearon los registros de los dos
sitios (Ejemplo 7 filas, demo 16): son **tandas automáticas**, no demanda real — 5 de las 7 de Ejemplo comparten
sesión y vienen de la página de pago con `?convoca_gateway_pago=12226`, y en la demo 11 de 16 llevan el
mismo `user_agent_hash` dentro de una tanda de 40 minutos de julio, con `clicked = 0` y (en Ejemplo) `score`
idéntico de 0,9. Por eso `frecuencia` va a `null`: **fingir una frecuencia que no existe sería peor que no
tenerla**.

Estado actual del set: **34 consultas** — 14 de intención corta y coloquial (la forma en que pregunta la
gente, donde se espera que el ranking falle), 20 tomadas del **título de una FAQ del corpus** (control: la
respuesta es la propia FAQ, así que si estas fallan el problema es el motor y no el etiquetado) y 1 hueco
medido sin respuesta esperada.

**Regla dura:** el set **no es referencia válida hasta que JC lo revise**. Un set mal etiquetado mide ruido
con toda la precisión del mundo. Las consultas que escriba él entran con `origen: "manual_jc"`.

Para añadir una consulta: (1) la frase, tal como la diría una persona; (2) los `id` esperados, **sacados del
corpus congelado** y verificados contra él (si el `id` no está, el arnés falla: es un error de etiquetado,
no un fallo del buscador); (3) de dónde sale, en `origen`; (4) `revisar: true` si no lo ha visto JC.

---

## 6. Los dos arneses

Los dos leen **los mismos** ficheros y **la misma** configuración, para que la comparación signifique algo.

### Servidor — `tests/Unit/SearchQualityTest.php`

- PHPUnit 10, sin WordPress: `tests/bootstrap.php` ya simula `get_option` sobre
  `$GLOBALS['_assistant_options']`, así que la configuración se fija ahí, de forma determinista.
- `Searcher::load_index()` lee `CONVOCA_ASSISTANT_INDEX_DIR . 'index.json'`, así que el arnés copia el
  corpus congelado (y el grafo) a ese directorio antes de medir.
- Llama a `Searcher::search()` y anota la posición de cada `esperado`.

### Cliente — `tests/search-quality-client.test.js`

- Jest 29 + jsdom, como el test de cliente que ya existe (`tests/assistant-chat.test.js`).
- Carga `assets/js/fuse.bundle.js` (el Fuse real, no un mock) y `assets/js/assistant-chat.js`, e instancia
  `window.ConvocaChat`.
- **Llama a `ConvocaChat.search(query)`**: el mismo camino que el widget. No se reimplementa el pipeline.
- Misma configuración que el servidor (umbral Fuse, `direct_threshold`, `priority_boost`), para que lo
  único que cambie entre las dos columnas sea el motor.

### Salida

Un comando único imprime, por motor: la tabla por consulta (consulta / esperado / devuelto / posición /
resultado) y el resumen de métricas. La tabla de fallos es el material de trabajo de las fases siguientes:
ninguna consulta queda sin diagnosticar — acierta en la posición N, falla, o el índice no la cubre.

Comandos previstos (regla: **nada encendido por defecto**, el buscador se queda como está):

```
composer test:quality        # los dos motores + resumen
composer phpunit             # suite unitaria, incluida la de calidad
cd tests && npx jest search-quality-client
```

---

## 7. Línea base (medida el 27/09/2026)

Se mide con `composer test:quality` (PHP 8.5 · Node 26 · Fuse 7.1.0, el bundle del sitio). Corre sin
red y sin WordPress, contra el corpus congelado.

| Resumen | Consultas | Recall@1 | Recall@3 | MRR@10 | nDCG@5 |
|---|---|---|---|---|---|
| **Servidor** (`Searcher`) | 33 | **81,8 %** | **90,9 %** | **0,878** | **0,898** |
| **Cliente** (Fuse + pipeline propio) | 33 | **78,8 %** | **87,9 %** | **0,849** | **0,879** |

(Cada motor mide 33 consultas puntuables; la 34.ª, «¿Qué servicios ofrecéis?», no tiene respuesta
esperada y se cuenta aparte: el servidor **devuelve algo** para ella —10 entradas— y el registro decía
que el cliente no encontraba respuesta. Sin respuesta correcta conocida no puede puntuar.)

**Lo que no acierta a la primera** — éste es el material de trabajo de la Fase 1:

| Consulta | Servidor | Cliente |
|---|---|---|
| `cuota` | pos 1 | **FALLA** (ni en el top-10) |
| `voluntariado` | pos 7 | pos 5 |
| `huerto` | pos 3 | pos 3 |
| `cocina` | pos 4 | pos 4 |
| `eventos` | pos 4 | pos 4 |
| `asamblea` | pos 2 | pos 2 |
| `contacto` | pos 2 | pos 2 |

**Los dos motores discrepan en dos consultas, y cada uno falla en la que el otro acierta:**

- **`cuota`**: el servidor devuelve la FAQ de cuota (`12154`) **primera**; el cliente **falla entera**
  (devuelve `12051`, `12088`, `9513`, `12539`, `12477`). Es una palabra sola y corta, el caso donde Fuse
  con umbral 0,4 y sin el cuerpo del texto no encuentra la FAQ.
- **`voluntariado`**: al contrario. El cliente lo pone en la 5 y el servidor en la 7.

El grupo de control (las 20 consultas tomadas del título de una FAQ) acierta **20 de 20 a la primera** en
los dos motores. Eso confirma que el banco está bien montado: cuando la respuesta está bien etiquetada y
el título se parece a la consulta, los dos motores aciertan. Las que fallan son las coloquiales y cortas,
que es justo lo que el goal esperaba encontrar.

**Aviso de provisionalidad:** estos números son de un set **pendiente de revisión de JC** (ver §5). Al
revisarlo cambiarán. La Fase 1 no debe apoyarse en diferencias de un punto: los fallos de arriba son
gruesos y no dependen de un par de etiquetas discutibles.

### Notas de infraestructura del banco

Para correr el motor del servidor **sin WordPress** hizo falta completar `tests/bootstrap.php` con dos
simulaciones que faltaban y que usa `Searcher`: `remove_accents()` (equivalente a la del núcleo en el
rango latino) y la constante `DAY_IN_SECONDS`. Con la guarda de siempre, para no pisarlas si algún día se
corre con WordPress de verdad. Sin ellas el arnés ni arrancaba; no son un defecto del motor.

---

## 8. Hallazgos y decisiones de esta fase

- **Bug de esquema entre sitios (fuera del alcance de esta fase, se abre issue):** la tabla
  `wp_convoca_assistant_log` tiene **17 columnas en Ejemplo** (`response`, `answered`, `results_count`,
  `time_ms`, `user_ip`, `user_agent` de versiones viejas) y **11 en la demo**. Las migraciones del plugin
  no han corrido igual en los dos sitios. Los `user_ip` / `user_agent` de Ejemplo están **vacíos** en todas
  las filas: son columnas muertas que conviene retirar en una migración de verdad.
- **Nada se enciende por defecto.** El motor compuesto y el Fuse actual siguen siendo el comportamiento en
  producción durante toda la Fase 0.
- **Medir el cliente exige su pipeline completo**, no Fuse suelto (ver §2).
- **Sin consultas reales**, el set es una propuesta. Las métricas de la primera pasada son
  **provisionales** y hay que repetirlas cuando JC revise el set: cambiar el set cambia los números.
