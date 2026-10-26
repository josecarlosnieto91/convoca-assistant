## [0.2.23] — 2026-10-26

### Cambiado

- **buscador, columnas y grafo**
- El buscador encuentra una pregunta natural sobre una entrada.
- La columna de fecha deja de inventarse cuando el listado no la tiene.
- El grafo tolera una relacion sin peso.

## [0.2.22] — 2026-10-21

### Cambiado

- **Cinco ficheros de prueba que no ejecutaba nadie pasan a la suite real.** Vivían en `tests/` como
  `test-*.php` con la clase base de WordPress y **no los invocaba ni el CI ni `composer`**: eran cobertura de
  mentira. Cuatro se han portado a `tests/Unit` (Indexer, Installer, Searcher) con `PHPUnit\Framework\TestCase`
  y lo que usaban de WordPress ya lo daban los dobles del bootstrap; sus comprobaciones pasan y suman 37 tests.
- **Ocho comprobaciones quedan retiradas y documentadas** (issue #18): no pasaban al ejecutarlas por primera
  vez, y no se sabe si el código cambió de comportamiento o la prueba se quedó atrás. Se sacan a la luz en el
  issue en vez de dejarlas ahí en verde falso: el Starter Pack (5 errores + 1 fallo), la estructura del grafo
  y el cálculo de la puntuación del buscador.
- **Fuera `wp-phpunit/wp-phpunit` y `yoast/phpunit-polyfills`**: solo existían para esas pruebas.

## [0.2.21] — 2026-10-16

### Arreglado
- **La FAQ del aparcabicis no se recuperaba con una consulta coloquial (issue #14).** Las consultas que
  se lanzan al motor son los tokens juntos, los bigramas y los trigramas: con «¿dónde dejo la bici?» se
  buscaba «donde dejo bici» y sus trozos, pero **nunca «bici»**, que es justo la palabra que la FAQ tiene
  en sus palabras clave. Fuse no casa un patrón de 19 caracteres con una palabra de 4. Ahora, además de
  las consultas de siempre, se **rescatan las entradas cuya palabra clave contiene el token tal cual**.
  Es quirúrgico y no un segundo barrido: lanzar los tokens sueltos al motor arreglaba el caso pero metía
  tanto candidato que la recall@3 del arnés de calidad caía 3 puntos; con el rescate por palabra clave,
  el arnés queda **idéntico** (78,8 / 90,9 / 0,858 / 0,883 servidor · 78,8 / 87,9 / 0,849 / 0,879
  cliente, medido con y sin el cambio).

### Pruebas
- La batería de la demo lleva ahora la aserción del caso: «¿dónde dejo la bici?» tiene que devolver
  **3019** en primer lugar.

## [0.2.20] — 2026-10-16

### Arreglado
- **«Sin respuesta» daba por contestada cualquier consulta** (convoca-assistant#13), así que la pantalla se
  quedaba vacía y no podía volver a llenarse: el criterio llamaba a la búsqueda **sin umbral**, mientras la
  búsqueda del sitio —y el widget— descartan por debajo del suyo. En la demo, una consulta que puntuaba 0,1033
  con el umbral del sitio en 0,15 aparecía como resuelta cuando estaba registrada como `response_found = 0`.
  Ahora el umbral vive en `Searcher::umbral_configurado()` —**una sola fuente**— y lo usan la pantalla y la
  búsqueda por REST: es exactamente el umbral con el que la búsqueda decidió que no había respuesta, así que la
  pantalla no puede contradecir a su propio registro.

### Notas
- Dos pruebas nuevas del umbral (el del sitio, y el de fábrica cuando no hay ninguno configurado). Total: 36.
