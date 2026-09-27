# Changelog — Convoca Assistant

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
