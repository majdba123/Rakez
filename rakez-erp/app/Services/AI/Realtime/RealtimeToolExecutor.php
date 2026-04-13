<?php

namespace App\Services\AI\Realtime;

use App\Events\AI\AiToolExecuted;
use App\Models\AiRealtimeSession;
use App\Services\AI\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealtimeToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     * @return array{tool_name: string, call_id: string, output: string, denied: bool}
     */
    public function execute(AiRealtimeSession $session, array $item): array
    {
        $toolName = is_string($item['name'] ?? null) ? $item['name'] : 'unknown_tool';
        $callId = is_string($item['call_id'] ?? null) ? $item['call_id'] : 'missing_call_id';
        $toolStart = microtime(true);

        try {
            $args = json_decode((string) ($item['arguments'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (Throwable) {
            $args = ['error' => 'invalid_tool_arguments'];
        }

        $args = is_array($args) ? array_filter($args, fn ($value) => $value !== null) : [];
        $out = $this->toolRegistry->execute($session->user()->firstOrFail(), $toolName, $args);
        $result = $out['result'] ?? ['error' => 'Invalid tool result'];
        $denied = isset($result['allowed']) && $result['allowed'] === false;
        $durationMs = round((microtime(true) - $toolStart) * 1000, 2);

        Log::info('Realtime AI tool executed', [
            'tool_name' => $toolName,
            'user_id' => $session->user_id,
            'duration_ms' => $durationMs,
            'denied' => $denied,
            'correlation_id' => $session->correlation_id,
        ]);

        event(new AiToolExecuted(
            userId: $session->user_id,
            toolName: $toolName,
            durationMs: (float) $durationMs,
            denied: $denied,
            correlationId: $session->correlation_id
        ));

        return [
            'tool_name' => $toolName,
            'call_id' => $callId,
            'output' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'denied' => $denied,
        ];
    }
}
