---
title: Installation
---

# Installation

## Requirements

- PHP ^8.2
- Laravel ^11 or ^12
- laravel/ai ^0.2
- agenticmorf/laravel-ai-model-manager
- Cognee API (self-hosted or cloud)

## Composer

```bash
composer require agenticmorf/laravel-ai-cognee
```

[Packagist](https://packagist.org/packages/agenticmorf/laravel-ai-cognee)

## Migrations

The package loads migrations from its own `database/migrations` directory. Run:

```bash
php artisan migrate
```
