# Convoca Assistant — Hooks Reference

## Actions

| Hook | Description | Params |
|------|-------------|--------|
| `convoca_assistant/before_index` | Fires before index regeneration begins | — |
| `convoca_assistant/after_index` | Fires after index regeneration completes | `$index` (array) — The complete index |
| `convoca_assistant/before_search` | Fires before a server-side search | `$query` (string) — The raw query |
| `convoca_assistant/log_interaction` | Fires when an interaction is logged | `$data` (array) — Log entry data |

## Filters

| Filter | Description | Params |
|--------|-------------|--------|
| `convoca_assistant/sources` | Modify the list of active post type sources | `$sources` (string[]) — Post type slugs |
| `convoca_assistant/index_data` | Modify collected entries before they are written to the index | `$entries` (array) — Collected entries |
| `convoca_assistant/index_url` | Modify the URL of the generated index file (for cache busting) | `$url` (string) — Index file URL |
| `convoca_assistant/search_score` | Modify the composite score for an entry during search | `$score` (float), `$entry` (array), `$query` (string) |
| `convoca_assistant/synonyms` | Add or override synonym entries | `$synonyms` (array) — Current synonyms |
| `convoca_assistant/stop_words` | Modify the stop words list | `$words` (string[]) — Current stop words |
| `convoca_assistant/widget_settings` | Modify settings passed to the frontend widget | `$settings` (array) — Widget settings |

## Usage Examples

### Adding custom content to the index

```php
add_filter( 'convoca_assistant/index_data', function ( $entries ) {
    $entries[] = array(
        'id'         => 999,
        'type'       => 'custom',
        'title'      => 'My Custom Entry',
        'content'    => 'This will be searchable.',
        'url'        => 'https://example.com/custom',
        'categories' => array( 'Custom' ),
        'tags'       => array(),
        'keywords'   => array( 'custom', 'example' ),
        'weight'     => 2.0,
        'date'       => current_time( 'mysql' ),
        'modified'   => current_time( 'mysql' ),
    );
    return $entries;
} );
```

### Adding a new source post type

```php
add_filter( 'convoca_assistant/sources', function ( $sources ) {
    $sources[] = 'my_custom_post_type';
    return $sources;
} );
```

### Custom synonym injection

```php
add_filter( 'convoca_assistant/synonyms', function ( $synonyms ) {
    $synonyms['miembro'] = array( 'socio', 'asociado', 'afiliado' );
    return $synonyms;
} );
```
