<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class RealtimeTransportManager
{
    public function __construct(
        private readonly RealtimeTransportEventStore $eventStore,
        private readonly RealtimeAuditService $auditService,
        private readonly RealtimeBridgeLeaseService $leaseService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function start(AiRealtimeSession $session): array
    {
        $metadata = $session->metadata ?? [];
        $bridge = $metadata['bridge'] ?? [];

        $hasActiveOwner = $session->bridge_owner_token !== null
            && ! $this->leaseService->isStale($session);

        if (
            in_array($bridge['status'] ?? null, ['starting', 'running', 'connecting', 'reconnecting'], true)
            && $hasActiveOwner
        ) {
            throw new AiAssistantException('Realtime bridge is already running for this session.', 'ai_realtime_bridge_conflict', 409);
        }

        $ownerToken = (string) Str::uuid();
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'ai:realtime-bridge',
            $session->public_id,
            '--owner-token='.$ownerToken,
        ], base_path());

        $process->setTimeout(null);
        $process->disableOutput();
        $process->start();

        $leased = $this->leaseService->acquire($session, $ownerToken, $process->getPid());

        $metadata['bridge'] = [
            'status' => 'starting',
            'pid' => $process->getPid(),
            'started_at' => now()->toIso8601String(),
            'stop_requested' => false,
        ];

        $leased->update([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ]);

        $this->auditService->record(
            $leased->fresh(),
            'transport_bridge_start_requested',
            ['pid' => $process->getPid()],
            $leased->status,
            $leased->status
        );

        return [
            'pid' => $process->getPid(),
            'status' => 'starting',
        ];
    }

    public function stop(AiRealtimeSession $session, string $reason = 'stop_requested'): void
    {
        $metadata = $session->metadata ?? [];
        $metadata['bridge'] = array_merge($metadata['bridge'] ?? [], [
            'stop_requested' => true,
            'stop_requested_at' => now()->toIso8601String(),
        ]);

        $session->update([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ]);

        $this->eventStore->requestStop($session->fresh(), [
            'reason' => $reason,
        ]);
    }
}
