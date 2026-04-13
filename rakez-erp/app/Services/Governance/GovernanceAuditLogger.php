<?php

namespace App\Services\Governance;

use App\Models\GovernanceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GovernanceAuditLogger
{
    public function log(string $event, Model|string $subject, array $payload = [], ?User $actor = null): GovernanceAuditLog
    {
        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = $subject::class;
            $subjectId = $subject->getKey();
        } elseif (is_string($subject)) {
            $subjectType = $subject;
        }

        if (! isset($payload['ip_address']) && app()->runningInConsole() === false) {
            try {
                $payload['ip_address'] = request()->ip();
            } catch (\Throwable) {
                // No request context (queue, test) — skip silently
            }
        }

        return GovernanceAuditLog::create([
            'actor_id' => $actor?->getKey() ?? Auth::id(),
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
        ]);
    }
}
