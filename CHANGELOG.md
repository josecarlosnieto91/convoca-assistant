# Changelog — Convoca Assistant

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
