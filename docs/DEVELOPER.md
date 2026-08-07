# Convoca Assistant — Developer Guide

## Architecture Overview

Convoca Assistant has evolved from a simple FAQ chatbot into a **Knowledge Discovery Engine**.
The system is built on four layers:

```
┌─────────────────────────────────────────────────┐
│                 Knowledge Providers              │  ← Layer 0
│  (Posts, Pages, FAQ, KB, WooCommerce, Custom)   │
├─────────────────────────────────────────────────┤
│               Knowledge Graph (graph.json)       │  ← Layer 1
│         Weighted relations between content       │
├─────────────────────────────────────────────────┤
│             Search Engine + Clustering           │  ← Layer 2
│        n-grams, lemmas, composite scoring        │
├─────────────────────────────────────────────────┤
│            Session Memory (browser only)         │  ← Layer 3
│      Conversational context, no server data      │
└─────────────────────────────────────────────────┘
```

## Extending with Custom Knowledge Providers

Any plugin can register a new content source by implementing `Knowledge_Provider_Interface`:

### 1. Create your provider class

```php
<?php
/**
 * My Events Provider
 */

use Convoca\Assistant\Knowledge_Provider_Interface;

class My_Events_Provider implements Knowledge_Provider_Interface {

    public function get_id(): string { return 'my_events'; }
    public function get_name(): string { return 'Eventos'; }
    public function get_description(): string { return 'Eventos personalizados'; }
    public function is_available(): bool { return post_type_exists('evento'); }
    public function get_default_weight(): float { return 1.5; }
    public function get_setting_key(): string { return 'source_my_events'; }

    public function get_entries(int $max_content = 5000): array {
        $entries = [];
        $query = new \WP_Query([
            'post_type'      => 'evento',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            $entries[] = [
                'id'         => $post->ID,
                'type'       => 'my_events',
                'title'      => $post->post_title,
                'content'    => wp_strip_all_tags($post->post_content),
                'excerpt'    => get_the_excerpt($post),
                'url'        => get_permalink($post),
                'thumbnail'  => get_the_post_thumbnail_url($post, 'thumbnail') ?: '',
                'categories' => [],
                'tags'       => [],
                'keywords'   => [],
                'weight'     => 1.5,
                'date'       => $post->post_date,
                'modified'   => $post->post_modified,
            ];
        }
        return $entries;
    }

    public function get_entry_count(): int {
        return (new \WP_Query(['post_type' => 'evento', 'posts_per_page' => 1]))->found_posts;
    }

    public function get_relations(int $entry_id): array {
        // Return related content for the knowledge graph.
        // Each relation: ['to' => post_id, 'type' => 'same_category', 'weight' => 0.3]
        return [];
    }
}
```

### 2. Register via filter

```php
add_filter('convoca_assistant/providers', function ($providers) {
    $providers['my_events'] = new My_Events_Provider();
    return $providers;
});
```

### 3. Provider appears automatically

- In **admin > Conocimiento** as a toggleable source
- In **admin > Conocimiento** weight settings
- During index regeneration
- In the knowledge graph (via `get_relations()`)

## Extending the Knowledge Graph

Add custom relations between content nodes:

```php
add_filter('convoca_assistant/graph_data', function ($edges) {
    // Add a manual relation between post 42 and post 87
    $edges[] = [
        'from'   => 42,
        'to'     => 87,
        'type'   => 'custom_relation',
        'weight' => 0.8,
    ];
    return $edges;
});
```

## Available Hooks & Filters

See `docs/HOOKS.md` for the complete reference.

## REST API Extensions

Endpoints reales (namespace `convoca/v1`):

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/assistant/index` | Servir el índice generado |
| POST | `/assistant/search` | Buscar en la base de conocimiento (`{"query": "..."}`) |
| GET | `/assistant/stats` | Estadísticas de uso |
| GET | `/assistant/unanswered` | Preguntas sin respuesta |
| POST | `/assistant/log` | Registrar interacción |
| POST | `/assistant/rebuild-index` | Regenerar el índice (admin) |
| POST | `/assistant/clear-logs` | Limpiar logs (admin) |

## Running Tests

```bash
# PHPCS
composer run phpcs

# PHPStan (level 8)
composer run phpstan

# Jest (JS tests)
cd tests && npm install && npx jest

# Full suite
composer run test
```

## Architecture Decisions

### Why no AI?

Every response is traceable to real content on your site.
No hallucinations, no privacy leaks, no recurring costs.

### Why browser-only session memory?

GDPR compliance by design. No user data ever reaches your server.
Session data lives in localStorage and is cleared on browser close.

### Why Knowledge Providers?

Any plugin can add content sources without modifying core.
The indexer, grapher, and searcher all work with the same interface.
This makes the system infinitely extensible.
