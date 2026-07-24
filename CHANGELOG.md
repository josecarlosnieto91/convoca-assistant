# Changelog

Todos los cambios notables de Convoca Assistant se documentan aquí.

## [0.1.0] — 2026-07-24

### Añadido
- Widget flotante con búsqueda difusa Fuse.js v7.1
- Custom Post Types: FAQ (`convoca_faq`) y Base de Conocimiento (`convoca_kb`)
- Taxonomías: categoría de FAQ y categoría de KB
- Indexación automática de contenido: entradas, páginas, FAQs, KB y WooCommerce
- Algoritmo de scoring compuesto: fuzzy + exacto + sinónimos + stemming + cobertura + recencia + peso
- Stemmer ligero para español (+20 sufijos)
- Diccionario de sinónimos configurable y stop words
- Búsqueda servidor con Levenshtein (fallback)
- API REST con 7 endpoints y rate limiting (60 req/min)
- Dashboard con analíticas: consultas, tasa de resolución, tiempo medio, gráfico diario
- Gestión de fuentes de conocimiento con pesos por tipo de contenido
- Editor de sinónimos y stop words
- Consultas sin respuesta con acción "Crear FAQ"
- Exportación e importación de conocimiento y configuración (JSON con validación)
- Panel de ajustes: widget, búsqueda, privacidad, mantenimiento, debug
- Herramienta de búsqueda de depuración en el panel admin
- Modo oscuro automático (prefers-color-scheme)
- Accesibilidad WCAG (ARIA, teclado, focus management)
- Diseño responsive (pantalla completa en móvil)
- Renderizado Markdown en respuestas del chatbot
- Logging de interacciones con hash anónimo de IP
- Regeneración automática del índice ante cambios (con debounce de 30s)
- CRON de regeneración cada 5 minutos
- Configuración de privacidad GDPR
- Shortcode: `[convoca_assistant]`
- Dependencia opcional de Convoca Core (logging delegado, fallback a error_log)
- Fuse.js v7.1 bundlizado (26 KB)

### Técnico
- Autoloading PSR-4 via Composer
- Namespace PHP: `Convoca\Assistant\*`
- Módulos JavaScript ES6 (sin jQuery)
- WordPress Coding Standards (WPCS)
- PHPStan level 8
- PHPUnit: 3 suites, 10 tests
- Jest: 8 tests (jsdom)
- Archivos de documentación: ARCHITECTURE.md, API.md, HOOKS.md, RELEASE.md
- Archivo .pot con cadenas traducibles
- Checklist de release en docs/RELEASE.md
