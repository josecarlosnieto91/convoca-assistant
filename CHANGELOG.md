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

