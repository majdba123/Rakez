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
