# Changelog — Convoca Assistant

## v0.2.12 (2026-10-12)

### Arreglado
- **El buscador vuelve a responder** (issue #5). Medido con el motor real
  (`assets/js/assistant-chat.js`) y el `index.json` de produccion (347 entradas), sobre una
  bateria de 46 consultas coloquiales y 268 naturales, con `priority_boost` 1,35:
  | | antes (0,40) | despues (0,40) |
  |---|---|---|
  | coloquiales: directas / correctas / erroneas | 45 / 36 / 9 | 45 / 38 / 7 |
  | naturales: directas / correctas / erroneas | 257 / 241 / 16 | 259 / 246 / 13 |
  - **Umbral de respuesta directa a 0,40 por defecto.** Con 0,55 solo respondia una
    coincidencia exacta de titulo, porque `compositeScore()` comprime el rango y el maximo
    practico ronda 0,47: la misma bateria daba 29 directas con 0,55 y 45 con 0,40.
  - **Dedupe por la mejor puntuacion.** Una entrada que Fuse encuentra en varias
    formulaciones de la consulta se puntua con su mejor aparicion, no con la primera.
  - **Desempate a 0,90 entre pagina y FAQ.** Cuando ambas empatan en la puntuacion tope de
    coincidencia de titulo, gana la FAQ/wiki (la pagina es navegacion; la FAQ es la
    respuesta). La coincidencia exacta de titulo manda y permite responder con una pagina.
    Un desempate general a favor de FAQ/wiki se midio y se descarto: costaba 3 puntos de
    recall@1 (78,8% -> 75,8%) porque tambien movia empates donde ganaba una entrada de blog.
  - **El peso de una entrada solo refuerza coincidencias fuertes** (titulo o palabras clave
    exactos); en las flojas se usa el peso normal del tipo. Antes era un multiplicador
    global y subir el peso de una entrada le robaba consultas flojas a otras. _Nota para el
    sitio que tenga pesos artificiales por entrada: los pesos nativos por encima del normal
    del tipo dejan de dar ventaja salvo en coincidencia fuerte; conviene revisarlos._

## v0.2.11 (2026-10-12)

### Arreglado
- **Los comentarios del chat se servian en el HTML publico** de cada pagina: `<!-- Floating
  button -->`, `<!-- Chat window -->`, `<!-- Header -->`, `<!-- Messages -->`,
  `<!-- Suggestions -->` y `<!-- Input -->` viajaban al navegador de todos los visitantes,
  en todas las paginas. No se ven en pantalla, pero pesan, desvelan la implementacion
  interna y ensucian cualquier barrido del HTML. El nombre de cada clase ya dice que es
  cada bloque, asi que los comentarios no aportaban nada en la salida.

## v0.2.10 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v0.2.9 (2026-09-27)

### ✨ Añadido
- Motor de búsqueda conmutable: `search_engine` = `composite` (por defecto, el de siempre) o `fusion`
  (BM25 propio + Reciprocal Rank Fusion sobre posiciones). Ver `docs/buscador-fusion-spec.md`.
- Reordenación heurística del top-N (`search_rerank`, apagada por defecto). Ver
  `docs/buscador-rerank-spec.md`.
- Banco de pruebas del buscador: corpus congelado de Ejemplo, 34 consultas y arnés de métricas
  (Recall@1/3, MRR@10, nDCG@5) para el motor de servidor y para el de cliente. `composer test:quality`,
  en CI. Ver `docs/buscador-evaluacion-spec.md`.

### ⚖️ Medido, y por eso apagado
- La fusión **no** supera al compuesto (recall@1 72,7 % frente a 81,8 %) y el reranking **no** alcanza su
  criterio (nDCG@5 +0,002 cuando pedía +0,05). Ninguno de los dos se activa: el buscador sigue
  comportándose igual. Los ajustes existen, con su explicación, para que la medición se pueda repetir.

### 🐛 Corregido
- La constante `CONVOCA_ASSISTANT_VERSION` se había quedado en 0.2.7, así que los assets se servían con
  `?ver=0.2.7` y el índice declaraba esa versión antigua. Ahora coincide con la versión real.

## v0.2.8 (2026-09-23)

### 🐛 Correcciones
- El botón flotante del asistente no aparecía: `Widget::render_floating_widget()` existía pero nadie lo enganchaba, así que el widget solo salía en los sitios que traían su propio mu-plugin (Ejemplo tenía uno que lo pintaba en `shutdown`). Ahora el plugin lo pinta en el pie, una sola vez por petición.
- Con eso, el mu-plugin de Ejemplo deja de ser necesario: se retira.

## 0.2.2 (2026-09-05)

### 🔐 Security
- No indexar contenido protegido con contraseña + rate-limit por IP real

## 0.2.1 (2026-07-25)

### 🔧 Correcciones

- **Compatibilidad con temas sin `wp_footer`**: Añadidos hooks alternativos (`wp_body_open`, `wp_enqueue_scripts`) para que el widget se renderice incluso en temas que no ejecutan `wp_footer`.
- **Widget DOM desde JavaScript**: El HTML del widget se construye desde JS (`buildWidgetDOM()`) en lugar de inyectarse desde PHP, eliminando dependencia de `wp_footer`.
- **IDs duplicados**: Unificado a un solo hook de render para evitar que el widget aparezca múltiples veces en el DOM.
- **Padding del toggle**: Añadido `padding: 0 !important` para vencer estilos del theme (Bravada) que sobreescribían el botón del widget.
- **Cache-bust**: Bump a v0.2.1 para forzar refresco de assets CSS/JS en navegadores y CDN.

## 0.2.0 (2026-07-24)

### ✨ Nuevas funcionalidades

- **Saludos automáticos**: El asistente detecta saludos ("hola", "buenos días", "hey", "qué tal") y responde sin buscar en la KB.
- **Contenido relacionado como chips**: Los enlaces de contenido relacionado ahora son botones clickables que envían un nuevo mensaje en el chat, permitiendo seguir la conversación.
- **Detección de sesión mejorada**: El contexto "Antes preguntaste..." solo aparece cuando hay 2+ consultas en los últimos 10 minutos.
- **Sinónimos expandidos**: 7 grupos de sinónimos (contactar, hacerse, socio, cuota, actividad, reservar, funciona, informacion).

### 🔧 Correcciones

- **Icono de enviar**: Reemplazado SVG (no se renderizaba correctamente) por Unicode `►`.
- **Orden de carga JS**: `assistant-session.js` ahora se carga antes que `assistant-chat.js` para evitar `ReferenceError: convocaAssistant is not defined`.
- **Búsqueda Fuse.js**: La query original se prioriza antes que la expansión semántica (n-gramas, sinónimos), garantizando resultados incluso con queries cortas.
- **Grafo de conocimiento**: Añadidas aristas por tipo de contenido (weight 0.15) cuando no hay relaciones explícitas.
- **XSS en markdown**: Los enlaces markdown `[text](url)` solo permiten protocolos `https://`, `http://`, `mailto:`, `/` y `#`.

### ⚡ Rendimiento

- **Índice**: Eliminada compresión gzip del índice JSON para evitar problemas con nginx `gzip_static`.
- **API REST**: Respuestas en 2-7ms.

### 📚 Documentación

- Añadido `README.md` con guía de instalación, configuración y desarrollo.
- Añadido `CHANGELOG.md`.
- Documentación de shortcodes, REST API y providers.

---

## 0.1.0 (2026-07-23)

- Lanzamiento inicial.
- Motor de conocimiento local: 5 providers (FAQ, Posts, Pages, Taxonomies, Shortcodes).
- Búsqueda difusa con Fuse.js (threshold 0.4).
- Expansión semántica con n-gramas y sinónimos.
- Clustering de resultados.
- Memoria de sesión (últimas 2 consultas).
- Widget flotante con feedback (👍/👎/📋).
- REST API: `/convoca/v1/assistant/search`, `/log`, `/stats`, `/unanswered`.
- Panel de administración con estadísticas y gestión de sinónimos.
- Compatible GDPR: IPs anonimizadas (SHA256), sin cookies de terceros.
