# Convoca Assistant — REST API Reference

## Base URL

All endpoints are under `/wp-json/convoca/v1/assistant/`.

## Endpoints

### `GET /index`

Return the full knowledge index as JSON.

**Auth:** Public  
**Cache:** 5 min (Cache-Control: public)

### `POST /search`

Server-side fuzzy search.

**Body:**
```json
{ "query": "¿Cómo me registro?" }
```

**Response:**
```json
{
  "query": "¿Cómo me registro?",
  "results": [
    {
      "entry": { "id": 42, "title": "...", "url": "...", ... },
      "score": 0.87
    }
  ],
  "total": 1,
  "time_ms": 12.45
}
```

### `POST /log`

Log a user interaction.

**Body:**
```json
{
  "query": "¿Cómo me registro?",
  "response_found": true,
  "response_id": 42,
  "score": 0.87,
  "clicked": false,
  "time_ms": 5,
  "page_url": "https://example.com/"
}
```

### `GET /stats`

Admin analytics summary.

**Query params:** `days` (default: 30)

**Response:**
```json
{
  "total": 1234,
  "found": 1100,
  "not_found": 134,
  "resolution_rate": 89.1,
  "avg_score": 0.72,
  "avg_time_ms": 5.3,
  "top_queries": [...],
  "daily": [...]
}
```

### `GET /unanswered`

Admin list of unanswered queries.

**Query params:** `limit` (default: 50)

### `POST /rebuild-index`

Force regenerate the knowledge index.
