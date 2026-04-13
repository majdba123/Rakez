<?php

namespace App\Services\HR;

use App\Models\EmployeeWarning;
use App\Models\User;

class EmployeeWarningService
{
    public function createWarning(int $userId, array $data, User $actor): EmployeeWarning
    {
        User::findOrFail($userId);

        return EmployeeWarning::create([
            'user_id' => $userId,
            'issued_by' => $actor->id,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'is_auto_generated' => false,
            'warning_date' => $data['warning_date'] ?? now()->toDateString(),
        ]);
    }

    public function deleteWarning(int $warningId): void
    {
        EmployeeWarning::findOrFail($warningId)->delete();
    }
}
