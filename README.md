# agenticmorf/laravel-ai-cognee

Documentation is available on [GitHub Pages](https://agenticmorf.github.io/laravel-ai-cognee/).

Cognee integration for Laravel AI: RAG, conversation store, and content ingestion.

## Requirements

- PHP ^8.2
- Laravel ^11 or ^12
- laravel/ai ^0.2
- agenticmorf/laravel-ai-model-manager
- Cognee API (self-hosted or cloud)

## Installation

```bash
composer require agenticmorf/laravel-ai-cognee
```

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-ai-cognee-config
```

Set `config/laravel-ai-cognee.php`:

- **url** — Cognee API URL
- **api_token** — Cognee API token
- **datasets.resolver** — Closure returning dataset names/IDs for RAG and memory
- **memory.enabled** — Enable conversation memory pipe and search

## License

MIT
