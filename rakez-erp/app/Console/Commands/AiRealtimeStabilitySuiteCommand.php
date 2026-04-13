<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AiRealtimeStabilitySuiteCommand extends Command
{
    protected $signature = 'ai:realtime-stability-suite
        {--iterations=3 : Number of repeated smoke cycles}
        {--fail-fast : Stop on first failure}';

    protected $description = 'Run repeated backend realtime smoke cycles and report stability evidence.';

    public function handle(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $failFast = (bool) $this->option('fail-fast');

        $commands = [
            'ai:realtime-websocket-smoke-test',
            'ai:realtime-roundtrip-smoke-test',
            'ai:realtime-audio-buffer-smoke-test',
        ];

        $this->line('Pass criteria: every command succeeds in every iteration.');
        $this->line('Fail criteria: any command fails, hangs, or returns inconsistent transport evidence.');
        $this->line('Acceptable retry envelope: 0 automatic retries inside this suite.');
        $this->line('Acceptable reconnect envelope: no unexpected bridge conflict or session corruption during repeated cycles.');
        $this->newLine();

        $results = [];

        for ($iteration = 1; $iteration <= $iterations; $iteration++) {
            $this->info("Iteration {$iteration}/{$iterations}");

            foreach ($commands as $command) {
                $exitCode = Artisan::call($command);
                $output = trim(Artisan::output());

                $results[] = [
                    'iteration' => $iteration,
                    'command' => $command,
                    'exit_code' => $exitCode,
                ];

                if ($exitCode === self::SUCCESS) {
                    $this->line("  PASS {$command}");
                    continue;
                }

                $this->error("  FAIL {$command}");
                if ($output !== '') {
                    $this->line($output);
                }

                if ($failFast) {
                    return self::FAILURE;
                }
            }
        }

        $failures = array_filter($results, fn (array $result) => $result['exit_code'] !== self::SUCCESS);
        if ($failures !== []) {
            $this->error('Realtime stability suite failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Realtime stability suite passed.');
        $this->line("Completed smoke cycles: {$iterations}");
        $this->line('Controlled rollout acceptance criteria: repeated websocket/session bootstrap, text roundtrip, and audio-buffer ingress all stayed green without manual recovery.');

        return self::SUCCESS;
    }
}
