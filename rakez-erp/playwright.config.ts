import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from '@playwright/test';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const baseURL = 'http://127.0.0.1:8001';
const sqlitePath = path.join(__dirname, 'database', 'playwright.sqlite');

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 120_000,
    reporter: 'list',
    outputDir: 'tests/e2e/.artifacts',
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        viewport: {
            width: 1600,
            height: 1200,
        },
    },
    expect: {
        toHaveScreenshot: {
            animations: 'disabled',
            caret: 'hide',
            scale: 'css',
        },
    },
    webServer: {
        command: 'php tests/e2e/bootstrap-admin-visual-fixtures.php && php artisan serve --host=127.0.0.1 --port=8001',
        url: baseURL,
        timeout: 240_000,
        reuseExistingServer: false,
        env: {
            ...process.env,
            APP_ENV: 'playwright',
            APP_URL: baseURL,
            APP_FROZEN_NOW: '2030-01-01 09:00:00',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: sqlitePath,
            CACHE_STORE: 'array',
            QUEUE_CONNECTION: 'sync',
            SESSION_DRIVER: 'file',
            MAIL_MAILER: 'array',
            GOVERNANCE_TEMPORARY_PERMISSIONS_ENABLED: 'true',
        },
    },
});
