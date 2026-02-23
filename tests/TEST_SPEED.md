# Test Speed Guide

## Fastest: Parallel tests (recommended)

The project includes `brianium/paratest` as a dev dependency. Run the full suite in parallel across CPU cores. Expect **roughly 2–5x** faster than sequential on multi-core machines.

```bash
composer test:parallel
# or
php artisan test --parallel
```

Optional: set number of processes (default = number of CPU cores):

```bash
php artisan test --parallel --processes=4
```

**Note:** With `DB_DATABASE=:memory:` (SQLite in-memory), each parallel process gets its own database, so parallel runs are safe and do not require extra config.

---

## Standard: Sequential tests

```bash
composer test
# or
php artisan test
```

Use when debugging a single test or when parallel causes flakiness.

---

## What was tuned for speed

1. **phpunit.xml**
   - `cacheDirectory=".phpunit.cache"` – PHPUnit caches metadata (faster reruns).
   - `backupGlobals="false"` and `backupStaticAttributes="false"` – less work per test.
   - Existing: `BCRYPT_ROUNDS=4`, SQLite `:memory:`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` for tests.

2. **Composer**
   - `test:parallel` script runs `php artisan test --parallel` after clearing config.

3. **Database**
   - In-memory SQLite is already used; no disk I/O for the test DB.

---

## Running a subset (faster feedback)

```bash
# One suite
php artisan test --testsuite=Unit

# By name filter
php artisan test --filter=Accounting
php artisan test --filter=HrDashboard
php artisan test --filter=Deposit
```

---

## Optional: LazilyRefreshDatabase

For more speed in the future, you can switch from `RefreshDatabase` to `LazilyRefreshDatabase` in test classes. The database is then migrated only when a test in that class first touches the DB. This can reduce migration runs for classes that use the DB sparingly. Change only after verifying tests still pass.

---

## Real OpenAI API tests

The `AIAssistantRealOpenAITest` class contains E2E tests that hit the live OpenAI API. They are **skipped by default** and only run when two conditions are met:

1. A real `OPENAI_API_KEY` is set in `.env` (not the phpunit.xml placeholder).
2. `AI_REAL_TESTS=true` is set in `.env`.

### Local setup

```bash
# In your .env file:
OPENAI_API_KEY=sk-your-real-key-here
AI_REAL_TESTS=true
```

### Running the tests

```bash
# Run only the real-key tests
php artisan test --filter=AIAssistantRealOpenAI

# Run within the Integration suite
php artisan test --testsuite=Integration --filter=AIAssistantRealOpenAI
```

### What they cover

| Test | Endpoint | Validates |
|---|---|---|
| `test_real_ask_endpoint_returns_ai_response` | POST `/api/ai/ask` | HTTP 200, JSON contract, DB persistence (user + assistant rows, model/tokens/request_id) |
| `test_real_chat_continuity_in_same_session` | POST `/api/ai/chat` x2 | Session continuity, conversation history, 4+ DB rows |
| `test_real_v2_chat_returns_strict_json_schema` | POST `/api/ai/v2/chat` | Strict JSON schema (answer_markdown, confidence, sources, links, access_notes) |

### Cost

Each run makes ~3 API calls with short prompts and capped `max_output_tokens`. Estimated cost per run: **< $0.01**.

### CI setup

Add these as secrets/environment variables in your CI pipeline:

```yaml
env:
  OPENAI_API_KEY: ${{ secrets.OPENAI_API_KEY }}
  AI_REAL_TESTS: "true"
```

When `OPENAI_API_KEY` is missing or `AI_REAL_TESTS` is not `true`, the tests skip gracefully — they will not fail the build.
