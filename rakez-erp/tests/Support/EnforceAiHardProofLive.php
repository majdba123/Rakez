<?php

declare(strict_types=1);

/**
 * Guard script for hard-proof AI suites:
 * - Requires AI_REAL_TESTS=true
 * - Requires OPENAI_API_KEY present and not fake
 * - Rejects fake/mocking usage inside hard-proof integration tests
 */

$root = dirname(__DIR__, 2);
$envPath = $root.'/.env';

if (! file_exists($envPath)) {
    fwrite(STDERR, "Hard-proof guard failed: .env not found.\n");
    exit(1);
}

$env = file_get_contents($envPath);
if ($env === false) {
    fwrite(STDERR, "Hard-proof guard failed: cannot read .env.\n");
    exit(1);
}

$getEnv = static function (string $key, string $fallback = '') use ($env): string {
    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $env, $m) !== 1) {
        return $fallback;
    }
    return trim((string) $m[1], " \t\n\r\0\x0B\"'");
};

$realTests = strtolower($getEnv('AI_REAL_TESTS'));
$apiKey = $getEnv('OPENAI_API_KEY');

if (! in_array($realTests, ['1', 'true', 'yes', 'on'], true)) {
    fwrite(STDERR, "Hard-proof guard failed: AI_REAL_TESTS must be true in .env.\n");
    exit(1);
}

if ($apiKey === '' || $apiKey === 'test-fake-key-not-used') {
    fwrite(STDERR, "Hard-proof guard failed: OPENAI_API_KEY must be real (non-fake).\n");
    exit(1);
}

$files = [
    $root.'/tests/Integration/AI/AiRealQaHardProofToolsDecisionTest.php',
    $root.'/tests/Integration/AI/AiRealQaStreamParityTest.php',
    $root.'/tests/Integration/AI/AiRealQaToolFailureResilienceTest.php',
    $root.'/tests/Integration/AI/AiRealQaRetrievalHardCasesTest.php',
    $root.'/tests/Integration/AI/AiRealQaRoleDepthMatrixTest.php',
];

$badPatterns = [
    'OpenAI::fake(',
    'Http::fake(',
    'Mockery::',
    '->shouldReceive(',
    'partialMock(',
];

foreach ($files as $file) {
    if (! file_exists($file)) {
        fwrite(STDERR, "Hard-proof guard failed: missing file {$file}\n");
        exit(1);
    }
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "Hard-proof guard failed: cannot read {$file}\n");
        exit(1);
    }

    if (! str_contains($content, 'TestsWithRealOpenAiConnection')) {
        fwrite(STDERR, "Hard-proof guard failed: {$file} must use TestsWithRealOpenAiConnection.\n");
        exit(1);
    }

    foreach ($badPatterns as $pattern) {
        if (str_contains($content, $pattern)) {
            fwrite(STDERR, "Hard-proof guard failed: {$pattern} found in {$file}\n");
            exit(1);
        }
    }
}

fwrite(STDOUT, "Hard-proof live guard passed.\n");
exit(0);

