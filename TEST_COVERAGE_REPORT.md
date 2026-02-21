# Test Suite & Coverage Report

## Full test run summary

- **Total tests:** 928
- **Suites:** Unit + Feature
- **Runtime:** PHP 8.2.12 with Xdebug 3.5.1
- **Config:** `phpunit.xml` (SQLite `:memory:`, `BCRYPT_ROUNDS=4`, `AI_ENABLED=true`)

### Unit tests (run separately)

- **Count:** 187 tests, 426 assertions
- **Status:** All passing
- **Duration:** ~2–2.5 minutes

### Known issues in full run

- **Skipped (1):** `Tests\Feature\AI\AIAssistantIntegrationTest::test_throttle_ai_assistant` (throttle testing disabled in this environment).
- **Failure (1):** `Tests\Feature\Marketing\MarketingProjectTest::it_can_list_marketing_projects` — may need permission/role or API response shape adjustment.

---

## How to run tests

```bash
# All tests (no coverage)
php artisan test

# All tests with coverage (requires Xdebug in coverage mode)
php -d xdebug.mode=coverage artisan test --coverage

# Unit tests only (faster)
php artisan test tests/Unit

# Unit tests with coverage
php -d xdebug.mode=coverage artisan test tests/Unit --coverage

# Parallel run (faster, no coverage)
composer test:parallel
```

Using PHPUnit directly with text coverage:

```bash
vendor\bin\phpunit --coverage-text
```

---

## Coverage report

Code coverage is collected for the **`app`** directory (see `<source><include>` in `phpunit.xml`).

- **Driver:** Xdebug (or PCOV) must be loaded and, for Xdebug, in coverage mode (`xdebug.mode=coverage`).
- **Output:** With `--coverage`, PHPUnit prints a **text summary at the end** of the run with:
  - **Lines:** % of executable lines run
  - **Functions and Methods:** % of callables run
  - **Branches:** (if using Xdebug) % of branches taken

Full suite with coverage takes **about 15–25 minutes** depending on machine. The coverage block appears after the last test and looks like:

```
Code Coverage Report:
  2026-02-20 20:20:04

 Summary:
  Classes:  xx.xx% ( ... / 370 )
  Methods:  xx.xx% ( ... / 1813 )
  Lines:    xx.xx% ( ... / 16490 )
```

(Exact totals depend on your `app/` codebase size.)

### Saving the coverage report to a file

**PowerShell:**

```powershell
php -d xdebug.mode=coverage artisan test --coverage 2>&1 | Out-File -FilePath coverage-report.txt -Encoding utf8
# Then open coverage-report.txt and scroll to the end for the summary.
```

**Or with PHPUnit:**

```powershell
vendor\bin\phpunit --coverage-text 2>&1 | Out-File -FilePath coverage-report.txt -Encoding utf8
```

**Composer script (optional):**

```bash
composer test:coverage
```

This runs tests with coverage and generates `coverage.xml` (Clover). The script in `composer.json` may use `--min=100`; remove or lower that if you do not want the build to fail on low coverage.

---

## Summary

| Item              | Value                    |
|-------------------|--------------------------|
| Total tests       | 928                      |
| Unit tests        | 187 (all passing)        |
| Skipped           | 1 (throttle)             |
| Known failure     | 1 (MarketingProjectTest) |
| Coverage scope    | `app/`                   |
| Coverage driver   | Xdebug (coverage mode)   |

To get **numeric coverage (e.g. Lines %)**: run the full suite with `--coverage` and read the “Code Coverage Report” section at the end of the output (or at the end of the file if you redirect to `coverage-report.txt`).
