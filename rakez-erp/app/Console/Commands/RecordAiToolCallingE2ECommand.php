<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RecordAiToolCallingE2ECommand extends Command
{
    protected $signature = 'ai:record-e2e-responses
                            {--output= : Path to JSON file (default: storage/app/ai_e2e_recordings/run-{timestamp}.json)}
                            {--user= : User ID (default: first admin user or create one)}
                            {--also-run-tests : After recording, run composer test:e2e-ai (long)}';

    protected $description = 'Run all predicted tool-calling questions via RakizAiOrchestrator and save full JSON responses to a file.';

    public function handle(): int
    {
        if (! config('ai_assistant.enabled', true)) {
            $this->error('AI assistant is disabled (ai_assistant.enabled).');

            return self::FAILURE;
        }

        if (empty(config('openai.api_key'))) {
            $this->error('OPENAI_API_KEY is not set. Set it in .env before running.');

            return self::FAILURE;
        }

        $cases = config('ai_predicted_questions', []);
        if ($cases === []) {
            $this->error('config/ai_predicted_questions.php is empty.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        $this->info('Using user #'.$user->id.' ('.$user->email.')');

        $orchestrator = app(RakizAiOrchestrator::class);

        $outDir = storage_path('app/ai_e2e_recordings');
        File::ensureDirectoryExists($outDir);

        $path = $this->option('output');
        if (! is_string($path) || $path === '') {
            $path = $outDir.'/run-'.now()->format('Y-m-d_His').'.json';
        } elseif (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', $path)) {
            $path = $outDir.'/'.ltrim($path, '/');
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
            ],
            'orchestrator' => [
                'model' => config('ai_assistant.v2.openai.model'),
                'max_tool_calls' => config('ai_assistant.v2.tool_loop.max_tool_calls'),
            ],
            'cases' => [],
        ];

        $bar = $this->output->isDecorated()
            ? $this->output->createProgressBar(count($cases))
            : null;
        $bar?->start();

        foreach ($cases as $case) {
            $id = $case['id'] ?? 'unknown';
            $message = $case['message'] ?? '';
            $section = $case['section'] ?? null;

            $pageContext = [];
            if ($section !== null && $section !== '') {
                $pageContext['section'] = $section;
            }

            $record = [
                'id' => $id,
                'section' => $section,
                'message' => $message,
                'success' => false,
                'error' => null,
                'latency_ms' => null,
                'response' => null,
                'execution_meta' => null,
            ];

            $t0 = microtime(true);

            try {
                $result = $orchestrator->chat($user, $message, null, $pageContext);
                $record['latency_ms'] = (int) round((microtime(true) - $t0) * 1000);
                $record['success'] = true;

                $meta = $result['_execution_meta'] ?? null;
                if (isset($result['_execution_meta'])) {
                    unset($result['_execution_meta']);
                }
                $record['execution_meta'] = $meta;
                $record['response'] = $result;
            } catch (Throwable $e) {
                $record['latency_ms'] = (int) round((microtime(true) - $t0) * 1000);
                $record['error'] = [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                ];
            }

            $payload['cases'][] = $record;
            $bar?->advance();
        }

        $bar?->finish();
        $this->newLine();
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        $this->info('Wrote '.count($payload['cases']).' cases to: '.$path);

        if ($this->option('also-run-tests')) {
            $this->warn('Running composer test:e2e-ai (this may take many minutes)...');
            $process = new Process(['composer', 'run', 'test:e2e-ai'], base_path());
            $process->setTimeout(null);
            $process->run(function ($type, $buffer): void {
                echo $buffer;
            });

            return $process->getExitCode() === 0 ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveUser(): User
    {
        $userId = $this->option('user');
        if ($userId !== null && $userId !== '') {
            return User::query()->findOrFail((int) $userId);
        }

        $admin = User::role('admin')->first();
        if ($admin) {
            return $admin;
        }

        return $this->createBootstrapUser('admin');
    }

    private function createBootstrapUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        if ($roleName === 'admin') {
            $permissions = array_keys(config('ai_capabilities.definitions', []));
        } else {
            $permissions = config('ai_capabilities.bootstrap_role_map.'.$roleName, []);
        }

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $role->syncPermissions($permissions);

        $user = User::factory()->create([
            'email' => 'ai-e2e-'.uniqid().'@example.test',
            'type' => 'admin',
        ]);
        $user->assignRole($role);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $user->fresh();
    }
}
