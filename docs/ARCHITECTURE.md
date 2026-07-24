# Convoca Assistant — Architecture

## Overview

Convoca Assistant is a local, AI-free conversational assistant for WordPress.
It uses Fuse.js fuzzy search on a self-generated knowledge index to answer
user questions. No external APIs, no cloud services, fully GDPR compliant.

## Directory Structure

```
convoca-assistant/
├── convoca-assistant.php          # Plugin header + bootstrap
├── composer.json                  # PSR-4 autoload, dev deps
├── readme.txt                     # WordPress readme
├── uninstall.php                  # Cleanup
├── includes/                      # PSR-4: Convoca\Assistant
│   ├── class-installer.php        # Activation, DB schema, defaults
│   ├── class-knowledge-base.php   # CPTs, taxonomies, metadata, sources
│   ├── class-indexer.php          # Index generation + cron + triggers
│   ├── class-searcher.php         # Server-side fallback search
│   ├── class-admin.php            # Admin menu and pages
│   ├── class-settings.php         # Settings API
│   ├── class-statistics.php       # Interaction logging + analytics
│   ├── class-synonyms.php         # Synonyms and stop words
│   ├── class-cache.php            # Index cache management
│   ├── class-export-import.php    # Export/Import knowledge
│   ├── class-widget.php           # Shortcode + floating widget
│   ├── class-rest-controller.php  # REST API endpoints
│   └── class-i18n.php             # Translation loading
├── assets/
│   ├── js/
│   │   ├── fuse.bundle.js         # Fuse.js v7.1 (bundled)
│   │   ├── assistant-chat.js      # Chat engine (ES6)
│   │   ├── assistant-widget.js    # Widget UI (ES6)
│   │   └── admin-assistant.js     # Admin JS (ES6)
│   ├── css/
│   │   ├── assistant-widget.css   # Floating button + container
│   │   ├── assistant-chat.css     # Chat window + messages
│   │   └── admin-assistant.css    # Admin styles
│   ├── images/
│   └── templates/
│       ├── widget-html.php        # Widget HTML template
│       ├── admin-dashboard.php
│       ├── admin-knowledge.php
│       ├── admin-synonyms.php
│       ├── admin-analytics.php
│       ├── admin-unanswered.php
│       ├── admin-settings.php
│       └── admin-tools.php
├── languages/
├── tests/
├── dev-tools/
│   ├── phpcs.xml.dist
│   └── phpstan.neon.dist
└── docs/
    ├── ARCHITECTURE.md
    ├── HOOKS.md
    └── API.md
```

## Namespace: `Convoca\Assistant`

All classes follow PSR-4 autoloading via Composer, mapped to `includes/`.

## Data Flow

1. **Content is published/updated** → hooks fire → index marked dirty
2. **Cron job** regenerates JSON index every 5 minutes when dirty
3. **Index file** stored in `wp-upload/convoca-assistant/index.json` (optionally .gz)
4. **Frontend** downloads index via REST API or direct URL
5. **Fuse.js** in browser performs fuzzy search on the full index
6. **Results** rendered as rich chat messages with source links
7. **Interactions** logged anonymously to `wp_convoca_assistant_log`

## Search Flow (Client-side)

```
User query → normalize (lowercase, accents, stop words)
           → expand with synonyms
           → Fuse.js multi-key search (title×4, keywords×3, categories×2, content×1, tags×1)
           → composite score + weight factor
           → top N results sorted by score
           → render best result with source link
```

## Database

### Table: `wp_convoca_assistant_log`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | Auto-increment |
| session_id | varchar(64) | Browser session |
| query | text | User query |
| response_id | bigint | Matched post ID |
| response_found | tinyint | Was a result found? |
| score | float | Match score |
| clicked | tinyint | User clicked source? |
| query_time_ms | int | Search duration |
| page_url | varchar(512) | Page where query was made |
| user_agent_hash | varchar(64) | Anonymized UA |
| created_at | datetime | When logged |

## REST API

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| GET | /convoca/v1/assistant/index | Public | Return index JSON |
| POST | /convoca/v1/assistant/search | Public | Server-side search |
| POST | /convoca/v1/assistant/log | Public | Log interaction |
| GET | /convoca/v1/assistant/stats | Admin | Analytics |
| GET | /convoca/v1/assistant/unanswered | Admin | Unanswered queries |
| POST | /convoca/v1/assistant/rebuild-index | Admin | Force rebuild |

## Dependencies

- **Convoca Core** (required) — Logger, Utils
- **Fuse.js** v7.1 (bundled in assets/js/) — Frontend fuzzy search
- **PHP 8.1+** 
- **WordPress 6.4+**
