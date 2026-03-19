---
title: Configuration
---

# Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-ai-cognee-config
```

Edit `config/laravel-ai-cognee.php`:

- **url** — Cognee API URL (default: `http://cognee:8000`)
- **api_token** — Cognee API token (env: `COGNEE_API_TOKEN`)
- **timeout** — HTTP timeout in seconds
- **datasets.resolver** — Closure `(?string $conversationId, ?int $userId) => string[]` returning dataset names or UUIDs for RAG and memory search
- **memory.enabled** — Enable conversation memory pipe and search
- **memory.top_k** — Number of memory chunks to return (default: 5)
- **memory.search_timeout** — Cognee search timeout in seconds
- **debug_search_response** — Log search requests/responses (env: `COGNEE_DEBUG_SEARCH`)
