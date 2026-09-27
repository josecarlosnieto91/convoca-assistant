# Fase 1 — Fusión léxica: BM25 propio + Reciprocal Rank Fusion

Spec de la Fase 1 del goal `convoca-buscador-evaluacion-2026-09`. Va antes del código (regla 1).

- Parte de la línea base medida en `buscador-evaluacion-spec.md` (§7).
- **Estado del set de consultas: sin revisión final de JC.** Las métricas de este documento son
  **provisionales** por eso, y hay que volver a correrlas cuando el set cambie. No se inventan
  frecuencias ni etiquetas: las 34 consultas son las que hay, con su origen marcado.

---

## 1. El diagnóstico que justifica esta fase

La línea base deja dos cosas claras:

1. **Las consultas cortas y coloquiales son las que fallan.** `cuota`, `voluntariado`, `huerto`,
   `cocina`, `eventos`, `asamblea`, `contacto`. El grupo de control (títulos de FAQ) acierta 20 de 20.
2. **El motor compuesto mira el título y poco más.** Su peso dominante es Levenshtein sobre el título
   (`weights_fuzzy` 0,45), y el cuerpo del texto entra con 0,05 de cobertura. Una palabra como «cuota»
   aparece en el **cuerpo** de «¿Cuánto cuesta ser socio o colaborador?», no en su título.

BM25 puntúa por frecuencia de término dentro del documento, normalizada por longitud, y con IDF: si el
término es raro en el corpus, vale más. Es exactamente la señal que le falta al motor actual.

## 2. Qué se implementa

Nada de librerías y nada copiado de repositorios con licencia no comercial: BM25 y RRF se implementan
**desde su definición estándar**, que es pública y de sobra conocida.

### `includes/Bm25.php` — BM25 estándar

Para una consulta `Q` con términos `t` y un documento `D`:

```
score(D, Q) = Σ_t  IDF(t) · ( f(t,D) · (k1 + 1) ) / ( f(t,D) + k1 · ( 1 − b + b · |D| / avgdl ) )
```

- `f(t,D)` — frecuencia del término en el documento, contando **campos con peso**: el peso multiplica
  la frecuencia, así que un término en el título cuenta más que el mismo término en el cuerpo.
- `|D|` — longitud del documento (nº de términos, ya ponderada).
- `avgdl` — longitud media del corpus.
- `IDF(t) = ln( 1 + (N − df(t) + 0,5) / (df(t) + 0,5) )` — variante estándar que no da negativos
  (la de Lucene). Se documenta porque hay variantes: la clásica `log((N−df+0.5)/(df+0.5))` puede dar
  valores negativos con términos muy frecuentes y aquí no interesa.
- `k1 = 1,2` y `b = 0,75` por defecto, **configurables** por ajuste.

Campos y pesos: `title` 4 · `keywords` 3 · `categories` 2 · `content` 1 · `tags` 1 — **los mismos que ya
usa Fuse** en el cliente, para que las dos señales sean comparables y no se introduzca un criterio nuevo.

Tokenización: la que ya existe (`Searcher::normalize()` + stopwords del índice) **más la raíz** de cada
término (`stem_spanish`, que ya existe). Se indexan el término y su raíz, no uno u otro: así «cuota» casa
con «cuotas» y «renovar» con «renovación». Sin dependencias nuevas: el plugin va a WordPress.org.

### `includes/Fusion.php` — RRF

```
RRF(d) = Σ_r  1 / ( k + rank_r(d) )
```

- `rank_r(d)` — posición del documento en el ranking `r` (1 = primero). Si no aparece en ese ranking, no
  suma.
- `k = 60` por defecto, configurable. `k` amortigua cuánto pesa estar en cabeza.
- **Trabaja sobre posiciones, no sobre puntuaciones.** Eso es justo lo que hace falta aquí: el compuesto
  devuelve 0–1 y BM25 devuelve valores sin escala fija. Compararlos directamente sería un error; sus
  posiciones sí son comparables.
- Los rankings que se fusionan son **dos**: el compuesto actual (el de siempre, intacto) y el BM25 nuevo.
  Fusionar el motor viejo consigo mismo disfrazado no aportaría nada; lo que se busca es que las dos
  señales se cubran.

La puntuación RRF se normaliza (dividida por el máximo) solo para que el campo `score` que ya devuelve la
API siga en un rango comparable. **La normalización no cambia el orden.**

## 3. Cómo se enciende

- Ajuste nuevo `search_engine` con dos valores: **`composite` (por defecto, el de siempre)** y `fusion`.
- Ajustes numéricos nuevos: `search_bm25_k1` (1,2), `search_bm25_b` (0,75), `search_rrf_k` (60).
- **`composite` no cambia de comportamiento.** El arnés lo comprueba: con el motor por defecto, las 34
  posiciones tienen que salir **idénticas** a la línea base. Si no salen, el refactor rompió algo.
- `search_threshold` **no se aplica al motor `fusion`**, y hay que decirlo: el RRF no es una puntuación de
  similitud y compararlo con 0,1 no significa nada. Lo que decide qué entra es el propio ranking. Se
  documenta en la wiki y en la descripción del ajuste.
- Que el motor `fusion` pase a ser el de por defecto **no se decide en esta fase**: se decide con la tabla
  delante y es decisión de JC.
- El **cliente (Fuse) no se toca en esta fase.** La fusión es del motor de servidor. Ojo con lo que eso
  significa en producción: hoy `search_mode` es `client`, así que el servidor es el **respaldo**
  (sin JavaScript, crawlers, REST). La mejora se mide en el servidor y su efecto sobre lo que ve el
  visitante depende de una decisión posterior. Se deja escrito, no se disfraza.

## 4. Criterio de aceptación (el del goal)

Con `search_engine=fusion`:

1. **Recall@1 sube ≥ +10 puntos relativos** sobre la línea base (81,8 % → ≥ 90,0 %).
2. **Ninguna consulta del set empeora de posición.**

Si no se cumple, la fase se cierra **documentando por qué no** y no se despliega. Eso también es un
resultado válido: no se toca el defecto y se cuenta.

Medición: el mismo arnés de la Fase 0, con las tres configuraciones en la misma tabla
(`composite` / `bm25` / `fusion`), sobre el corpus congelado y el set de 34 consultas.

## 5. Resultado medido: la fase NO cumple el criterio (y por eso no se despliega)

Medido con `composer test:quality` contra el corpus congelado, con las **tres** configuraciones en una
tabla. Reproducible: `vendor/bin/phpunit --filter SearchQualityTest`.

| Motor | Recall@1 | Recall@3 | MRR@10 | nDCG@5 |
|---|---|---|---|---|
| **Compuesto** (el de siempre) | **81,8 %** | **90,9 %** | **0,878** | **0,898** |
| BM25 solo | 72,7 % | 81,8 % | 0,789 | 0,804 |
| **Fusión BM25 + RRF** | **72,7 %** | 87,9 % | 0,818 | 0,831 |

**El criterio pedía Recall@1 ≥ +10 % relativo (≥ 90,0 %) y ninguna consulta peor. Sale −11,1 % relativo
y seis consultas peor.**

Qué pasa consulta a consulta (posiciones, el detalle completo lo imprime el arnés):

| Consulta | Compuesto | BM25 | Fusión | |
|---|---|---|---|---|
| `cuota` | 1 | 5 | 2 | empeora |
| `taller` | 1 | 19 | 6 | empeora |
| `socio` | 1 | 6 | 2 | empeora |
| `cocina` | 4 | **2** | 5 | empeora |
| `eventos` | 4 | 5 | 7 | empeora |
| `asamblea` | 2 | 11 | 3 | empeora |
| `huerto` | 3 | 3 | **2** | mejora |
| `voluntariado` | 7 | 7 | **6** | mejora |
| Las 20 del grupo de control | 1 | 1 | 1 | igual |
| `¿Qué servicios ofrecéis?` | cubre | cubre | cubre | igual |

### Por qué (y no es un parámetro mal elegido)

1. **La fusión diluye una señal que ya era buena.** El compuesto acierta 27 de 33 a la primera; BM25,
   24 de 33, pero **no acierta los mismos**. RRF reparte, y al promediar una señal buena con otra peor,
   el resultado queda en medio: 24 de 33, exactamente lo que da BM25.
2. **No es cuestión de `k` ni de profundidad.** Se barrieron `k` = 60/30/10 y profundidad =
   100/50/20/5: **recall@1 sale 72,7 % en las doce combinaciones.** El veredicto no depende de haber
   elegido mal un número. (La profundidad se queda en el código porque con `depth` 5 el recall@3 baja y
   el nDCG@5 sube: es información útil para quien retome esto.)
3. **BM25 aporta justo donde el compuesto falla**, pero poco: arregla `cocina` (4→2) y empata en
   `darme de baja`, `mascotas`, `horario`, `inscribirme`. A cambio **destroza** `taller` (1→19) y
   `asamblea` (2→11): sin el título, el grafo y el boost de FAQ, el término suelto no distingue.
   El compuesto no es «Levenshtein sobre el título y poco más» como suponía el diagnóstico inicial de
   esta fase: el «exact-title priority» (0,90) y el grafo hacen más trabajo del que parecía.

### Decisión

- **No se despliega y el motor por defecto sigue siendo `composite`.** `search_engine=fusion` existe,
  está probado y está **apagado**.
- **No se pasa a la Fase 2.** El reranking del top-N parte de la suposición de que la fusión deja margen;
  no lo deja: la fusión ya va por detrás del compuesto. Añadir un reranker encima de un ranking peor no
  arregla el problema, lo esconde. Queda **descartada por la medición**, no por falta de tiempo.
- **La Fase 3 (chunking con cabecera) tampoco se ejecuta.** Exigía evidencia de que las entradas largas se
  diluyen, y no la hay: las entradas problemáticas son FAQs cortas, no documentos largos. **YAGNI.**
- **YAGNI aplicado ya en esta fase:** se implementó el indexado de la raíz de cada término y las métricas
  salieron **idénticas** (72,7 % con y sin raíz), así que se ha retirado: no compensa el trabajo de
  indexado. Lo mismo con el método público que lo exponía, que quedaba muerto.

### Lo que sí queda en el repositorio, y para qué

`includes/Bm25.php` y `includes/Fusion.php` son implementaciones propias, probadas y **apagadas**. Sirven
para lo siguiente: el diagnóstico dice que **el compuesto acierta donde BM25 falla y al revés**. Cualquier
intento futuro debería partir de esa tabla, no de cero. Un candidato razonable que esta medición **no**
justifica todavía: dar a BM25 un peso menor en la fusión (fusión ponderada), o limitar la fusión a las
consultas cortas, que es donde BM25 aporta. Ninguna de las dos cosas se implementa sin medirla primero.

## 6. Lo que queda pendiente y no se disfraza

| Punto | Estado |
|---|---|
| Revisión del set de 34 consultas | **PENDIENTE: humana (JC).** Las métricas de aquí son provisionales |
| Capturas de persona usando la aplicación | **PENDIENTE: humana** |
| Decidir si `fusion` pasa a ser el defecto | **PENDIENTE: decisión de JC**, con la tabla delante |
| Llevar la mejora al cliente (Fuse) | **Fuera de esta fase.** Decisión posterior |
