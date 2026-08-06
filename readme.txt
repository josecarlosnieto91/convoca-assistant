=== Convoca Assistant ===
Contributors: josecnr91
Tags: chatbot, search, knowledge-base, support, privacy
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local AI-free conversational assistant for WordPress. Fuzzy search with Fuse.js over your own knowledge base. No APIs, no cloud. 100% GDPR-friendly.

== Description ==

Convoca Assistant turns your WordPress content into an interactive conversational assistant. Unlike AI-based chatbots, this plugin works completely offline: no external connections, no third-party APIs, no LLM models.

= How it works =

The plugin automatically builds a JSON index of all your publishable content (posts, pages, FAQs, knowledge base, and WooCommerce products). When a visitor asks a question, Fuse.js searches this index using multilingual fuzzy search with accent support, typo tolerance, and synonyms.

= Key features =

* **No AI, no cloud** — Everything is local. No external calls, no recurring costs. Your content never leaves your server.
* **Extremely fast** — The index downloads once in the browser. Searches take less than 5ms.
* **Automatic knowledge base** — The index is generated from your existing content. No manual setup.
* **Modern floating widget** — Conversational interface with dark mode, responsive and accessible (WCAG).
* **Smart synonyms** — Define synonyms to improve search accuracy.
* **Built-in analytics** — Queries, resolution rate, average time, unanswered questions.
* **GDPR friendly** — Anonymous logging (hashed IP), configurable retention, option to disable logging.
* **Shortcode included** — `[convoca_assistant]` to embed the chat anywhere.
* **REST API** — 7 endpoints for integration with other applications.

== Installation ==

1. Upload the `convoca-assistant` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Convoca Assistant > Settings** to configure
4. The floating widget will appear automatically on your site

= Creating FAQ content =

The plugin registers two custom post types:

* **FAQ** (`convoca_faq`) — Frequently asked questions with answers
* **Knowledge Base** (`convoca_kb`) — Help articles

Both are managed from **Convoca Assistant > Knowledge** or directly from the posts menu.

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

1. Floating widget in the bottom-right corner
2. Open chat showing suggestions and messages
3. Admin panel: Dashboard with statistics
4. Synonym editor
5. Widget settings

== Changelog ==

= 0.2.1 =
* Fix: minor compatibility improvements

= 0.2.0 =
* New: Automatic greetings (hello, good morning, hey, how are you)
* New: Related content as clickable chips
* New: Expanded synonyms (7 groups: contact, join, member, fee, activity, information)
* New: README.md and CHANGELOG.md
* Fix: Send icon (SVG → Unicode ►)
* Fix: JS load order to avoid ReferenceError
* Fix: Search prioritizes original query before semantic expansion
* Fix: Graph with edges by content type
* Fix: XSS in markdown links
* Fix: Session context only with 2+ queries in last 10 minutes
* Fix: Index entry type (was always 'post', now uses real post_type)
* Fix: Removed gzip compression of JSON index

= 0.1.0 =
* First development version.
* Floating widget with Fuse.js v7.1
* CPTs: FAQ and Knowledge Base
* Automatic index from posts, pages, FAQs, KB, WooCommerce
* Composite scoring algorithm (fuzzy + exact + synonyms + stemming + recency + weight)
* Configurable synonyms and stop words
* REST API with 7 endpoints and rate limiting
* Complete admin panel (dashboard, knowledge, synonyms, analytics, settings)
* Knowledge and configuration export/import
* Dark mode, responsive, WCAG accessible
* GDPR compatible (anonymous logging)
* No external dependencies (everything local)

== Upgrade Notice ==

= 0.2.1 =
* Minor compatibility and stability improvements.
