<?php

namespace App\Services\AI\Realtime;

use App\Models\AiRealtimeSession;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Support\Facades\DB;

class RealtimeBridgeLeaseService
{
    public function acquire(AiRealtimeSession $session, string $ownerToken, ?int $pid = null): AiRealtimeSession
    {
        return DB::transaction(function () use ($session, $ownerToken, $pid) {
            /** @var AiRealtimeSession $locked */
            $locked = AiRealtimeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if (
                $locked->bridge_owner_token !== null
                && $locked->bridge_owner_token !== $ownerToken
                && ! $this->isStale($locked)
            ) {
                throw new AiAssistantException(
                    'Realtime bridge ownership conflict for this session.',
                    'ai_realtime_bridge_conflict',
                    409
                );
            }

            $locked->update([
                'bridge_owner_token' => $ownerToken,
                'bridge_owner_pid' => $pid,
                'bridge_started_at' => $locked->bridge_started_at ?: now(),
                'bridge_heartbeat_at' => now(),
                'last_activity_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function heartbeat(AiRealtimeSession $session, string $ownerToken): AiRealtimeSession
    {
        return DB::transaction(function () use ($session, $ownerToken) {
            /** @var AiRealtimeSession $locked */
            $locked = AiRealtimeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            $this->ensureOwner($locked, $ownerToken);

            $locked->update([
                'bridge_heartbeat_at' => now(),
                'last_activity_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function release(AiRealtimeSession $session, string $ownerToken): AiRealtimeSession
    {
        return DB::transaction(function () use ($session, $ownerToken) {
            /** @var AiRealtimeSession $locked */
            $locked = AiRealtimeSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id);

            if ($locked->bridge_owner_token !== $ownerToken) {
                return $locked;
            }

            $locked->update([
                'bridge_owner_token' => null,
                'bridge_owner_pid' => null,
                'bridge_started_at' => null,
                'bridge_heartbeat_at' => null,
                'last_activity_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function isOwnedBy(AiRealtimeSession $session, string $ownerToken): bool
    {
        return $session->bridge_owner_token === $ownerToken;
    }

    public function isStale(AiRealtimeSession $session): bool
    {
        $staleAfter = max(1, (int) config('ai_realtime.transport.bridge_stale_after_seconds', 30));

        return $session->bridge_heartbeat_at === null
            || $session->bridge_heartbeat_at->copy()->addSeconds($staleAfter)->isPast();
    }

    private function ensureOwner(AiRealtimeSession $session, string $ownerToken): void
    {
        if ($session->bridge_owner_token !== $ownerToken) {
            throw new AiAssistantException(
                'Realtime bridge ownership lost for this session.',
                'ai_realtime_bridge_owner_conflict',
                409
            );
        }
    }
}
