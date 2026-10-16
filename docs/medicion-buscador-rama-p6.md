# Medición de la rama `gauntlet/p6-assistant-busqueda` (issue #5)

Fecha: 16/10/2026. Rama medida: **`c53aeea`**. Base: **`92833fd`** (su ancestro común con `main`).
Pregunta: *¿este arreglo del buscador mejora el ranking, y hay que fusionarlo?*

Todo lo de abajo es reproducible con los ficheros del repo (batería congelada
`tests/fixtures/eval-queries.json`, corpus `index-ejemplo-20260927.json`).

## 1. NDCG@k con el arnés del proyecto — NEUTRO

`composer test:quality`, el mismo arnés en los dos árboles (el test de calidad y la batería son
idénticos en base y rama: comprobado con `git diff --stat 92833fd c53aeea -- tests/`).

| | Recall@1 | Recall@3 | MRR@10 | nDCG@5 |
|---|---|---|---|---|
| servidor · base | 78,8% | 90,9% | 0,858 | 0,883 |
| servidor · rama | 78,8% | 90,9% | 0,858 | 0,883 |
| cliente · base | 78,8% | 87,9% | 0,849 | 0,879 |
| cliente · rama | 78,8% | 87,9% | 0,849 | 0,879 |

**No se mueve nada, y eso no significa que el arreglo no haga nada: significa que este instrumento
no mira ahí.** El arnés mide **posiciones** con `search_threshold` fijado a **0,1 a propósito**
(«la Fase 0 mide, no exige»), y el arreglo es de **umbral de respuesta** (si el widget contesta o
no) y de **desempate** (quién va primero en un empate exacto). Un nDCG de posiciones no puede ver
ninguna de las dos cosas: es la limitación del instrumento, no un veredicto sobre el cambio.

## 2. Precisión del desempate página/FAQ — 0,0% → 100,0%

Medición propia sobre la misma batería, con el motor del cliente (`assets/js/assistant-chat.js`) y
el corpus congelado: se cuentan los empates con puntuación **≥ 0,90** entre una `page` y una FAQ/wiki
y se mira quién queda primero.

| | empates ≥0,90 | gana la página | gana la FAQ/wiki | precisión del desempate |
|---|---|---|---|---|
| **base 92833fd** | 3 (1 consulta) | **3** | 0 | **0,0%** |
| **rama c53aeea** | 3 (1 consulta) | 0 | **3** | **100,0%** |

Caso concreto, en la consulta **«voluntariado»**: `convoca_faq = 0,9000` y `page = 0,9000`. La base
devuelve **la página** (navegación); la rama devuelve **la FAQ** (la respuesta). Hay un caso real en
el set de referencia, y el arreglo le da la vuelta.

## 3. Los tests del arreglo muerden (positivo y negativo)

| | base 92833fd | rama c53aeea = `main` |
|---|---|---|
| `BusquedaRankingTest` (PHP) | **2 fallos** | OK |
| `assistant-chat-busqueda.test.js` (cliente) | **3 de 5 fallan** | 5 OK |

Fallos en la base, con su cifra: «el peso no desempata en coincidencia floja» (0,4151 frente a
0,6591) y «la FAQ debe ganar el empate a 0,90» (10 frente a 20).

## 4. Veredicto: el arreglo es real, y NO hay que fusionar la rama

El arreglo funciona (punto 2 y 3), pero **`main` ya lo tiene**: su `Searcher.php` es el de la rama
más `umbral_configurado()` (la función de la 0.2.20, posterior), y `assets/js/assistant-chat.js` es
**idéntico** en los dos (mismo md5). Los tests de la rama están en `main` y pasan; contra la base,
fallan.

Fusionarla habría **revertido 485 líneas** de trabajo posterior de `main` (el `Starter_Pack`, los
cambios de `Admin`/`Statistics`, tests y CI). La rama es un **fósil**, no un pendiente: se borró
dejando aquí la evidencia y el hash `c53aeea`.

## 5. Lo que este informe NO mide

El **umbral de respuesta directa** (0,55 → 0,40): decide si el widget contesta o se queda callado,
no dónde queda cada resultado, y ningún arnés del repo lo mide. El CHANGELOG de la rama da unas
cifras (45/38/7 frente a 45/36/9) medidas con el índice de producción, que **no está en el repo** y
por tanto **no son reproducibles** desde aquí: quedan como declaración del commit, no como medida
propia. El umbral tampoco está en juego para decidir la fusión: `main` ya trae el mismo valor.
