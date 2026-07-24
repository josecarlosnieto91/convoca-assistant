=== Convoca Assistant ===
Contributors: josecnr91
Tags: chatbot, assistant, fuzzy-search, conversational, support, knowledge-base, gdpr, no-ai, local, fusejs
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Asistente conversacional local sin IA para WordPress. Búsqueda difusa con Fuse.js sobre tu propia base de conocimiento. Sin APIs, sin cloud. 100% GDPR.

== Description ==

= Asistente Virtual 100% Local =

Convoca Assistant transforma tu contenido de WordPress en un asistente conversacional interactivo. A diferencia de los chatbots basados en IA, este plugin funciona completamente sin conexiones externas, sin APIs de terceros, y sin modelos LLM.

= Cómo funciona =

El plugin genera automáticamente un índice JSON con todo tu contenido publicable (entradas, páginas, FAQs, base de conocimiento y productos WooCommerce). Cuando un visitante hace una pregunta, Fuse.js busca en este índice utilizando búsqueda difusa multilingüe con soporte para acentos, errores ortográficos y sinónimos.

= Características principales =

* **Sin IA, sin cloud** — Todo es local. Sin llamadas externas, sin costes recurrentes. Tu contenido nunca sale de tu servidor.
* **Extremadamente rápido** — El índice se descarga una vez en el navegador. Las búsquedas tardan menos de 5ms.
* **Base de conocimiento automática** — El índice se genera desde tu contenido existente. Sin configuración manual.
* **Widget flotante moderno** — Interfaz conversacional con modo oscuro, responsive y accesible (WCAG).
* **Sinónimos inteligentes** — Define sinónimos para mejorar la precisión de las búsquedas.
* **Analytics integrados** — Consultas, tasa de resolución, tiempo medio, preguntas sin respuesta.
* **GDPR friendly** — Logging anónimo (IP hasheada), retención configurable, posibilidad de desactivar logs.
* **Shortcode incluido** — `[convoca_assistant]` para incrustar el chat en cualquier lugar.
* **API REST** — 7 endpoints para integración con otras aplicaciones.

== Installation ==

1. Upload the `convoca-assistant` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Convoca Assistant > Ajustes** to configure
4. The floating widget will appear automatically on your site

= Para crear contenido FAQ =

El plugin registra dos tipos de contenido personalizados:

* **FAQ** (`convoca_faq`) — Preguntas frecuentes con respuesta
* **Base de Conocimiento** (`convoca_kb`) — Artículos de ayuda

Ambos son gestionables desde **Convoca Assistant > Conocimiento** o directamente desde el menú de entradas.

== Frequently Asked Questions ==

= Does this use AI? =

No. Convoca Assistant uses fuzzy search (Fuse.js) to match questions against your content. No LLMs, no external APIs, no cloud services. Everything runs locally in the browser.

= Is it GDPR compliant? =

Yes. All searches happen client-side. Logging is anonymous (SHA-256 hashed User-Agent), retention is configurable (default 90 days), and you can disable logging entirely.

= Does it work with caching plugins? =

Yes. The index is a static JSON file served via a REST endpoint that bypasses page cache. The widget JavaScript is enqueued normally.

= Can I customize the design? =

Yes. The widget supports custom primary color, title, greeting message, and position. It also automatically adapts to the user's system dark mode preference.

= Does it require Convoca Core? =

No. Convoca Core is optional. Without it, the plugin works standalone using its own logger. With Convoca Core, it integrates into the Convoca ecosystem for centralized logging.

= Can I add WooCommerce products? =

Yes. If WooCommerce is active, you can enable products as a knowledge source from the settings page.

== Screenshots ==

1. Widget flotante en la esquina inferior derecha
2. Chat abierto mostrando sugerencias y mensajes
3. Panel de administración: Dashboard con estadísticas
4. Editor de sinónimos
5. Ajustes del widget

== Changelog ==

= 0.1.0 =
* Primera versión de desarrollo.
* Widget flotante con Fuse.js v7.1
* CPTs: FAQ y Base de Conocimiento
* Índice automático desde posts, pages, FAQs, KB, WooCommerce
* Algoritmo de scoring compuesto (fuzzy + exacto + sinónimos + stemming + recencia + peso)
* Sinónimos y stop words configurables
* API REST con 7 endpoints y rate limiting
* Panel de administración completo (dashboard, conocimiento, sinónimos, analytics, ajustes)
* Export/Import de conocimiento y configuración
* Modo oscuro, responsive, WCAG accesible
* Compatible GDPR (logging anónimo)
* Sin dependencias externas (todo local)

== Upgrade Notice ==

= 0.1.0 =
Versión inicial.
