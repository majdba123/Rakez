<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Rakiz AI Assistant v2

The Rakiz AI Assistant v2 provides tool-calling, RAG over record summaries and documents, and strict JSON responses. It is single-tenant only (no `tenant_id`).

### Environment variables

- `OPENAI_API_KEY` – Required for chat and embeddings.
- `OPENAI_MODEL` – Model for chat (e.g. `gpt-4.1-mini`).
- `OPENAI_EMBED_MODEL` – Model for embeddings (e.g. `text-embedding-3-small`).
- `OPENAI_MAX_OUTPUT_TOKENS` – Max tokens for the assistant reply (e.g. `2000`).
- `AI_ASSISTANT_ENABLED` – Set to `false` to disable the v2 assistant (default: follows `AI_ENABLED`).

### Setup

1. Run migrations: `php artisan migrate` (creates `ai_documents` and `ai_chunks`).
2. Ensure the queue worker is running: `php artisan queue:work` (indexing jobs run on the queue).
3. Add the scheduler to cron: `* * * * * php artisan schedule:run`
   - `ai:reconcile-index` runs every 5 minutes.
   - `ai:daily-reindex` runs nightly (e.g. 02:00).

### Enabling / disabling

- Set `AI_ASSISTANT_ENABLED=false` in `.env` to disable the v2 assistant. Existing `/api/ai/chat` and `/api/ai/ask` are unchanged.

### SQLite and RAG

- With SQLite, the default `vector_driver` is `disabled` (tools-only mode). For RAG on SQLite you would need an external vector store or switch to MySQL/PostgreSQL with the `json` driver (or Postgres with pgvector).

### API endpoints (v2)

- `POST /api/ai/v2/chat` – Main chat (strict JSON response). Requires `use-ai-assistant` and `auth:sanctum`.
- `POST /api/ai/v2/search` – RAG sources only.
- `POST /api/ai/v2/explain-access` – Route/record access explanation.

### Observability

- Logs include `request_id`, `user_id`, `tool_calls`, `latency_ms`, token usage, and errors (with secrets redacted).

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
