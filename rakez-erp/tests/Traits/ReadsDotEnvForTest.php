<?php

namespace Tests\Traits;

/**
 * Read variables from project .env file (bypasses phpunit.xml OPENAI_API_KEY override).
 */
trait ReadsDotEnvForTest
{
    protected function envFromDotFile(string $key): ?string
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $prefix = $key.'=';

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, $prefix)) {
                $v = trim(substr($line, strlen($prefix)), '"\'');

                return $v !== '' ? $v : null;
            }
        }

        return null;
    }

    protected function envFromDotFileIsTrue(string $key): bool
    {
        $v = strtolower($this->envFromDotFile($key) ?? '');

        return in_array($v, ['true', '1', 'yes'], true);
    }
}
