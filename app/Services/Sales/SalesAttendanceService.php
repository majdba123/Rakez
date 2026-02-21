<?php

namespace App\Services\Sales;

use App\Models\SalesAttendanceSchedule;
use App\Models\User;
use Illuminate\Support\Collection;

class SalesAttendanceService
{
    /**
     * Get my attendance schedules.
     */
    public function getMyAttendance(User $user, array $filters): Collection
    {
        $query = SalesAttendanceSchedule::where('user_id', $user->id)
            ->with('contract');

        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->where(function ($q) use ($filters) {
                if (!empty($filters['from'])) {
                    $q->whereDate('schedule_date', '>=', $filters['from']);
                }
                if (!empty($filters['to'])) {
                    $q->whereDate('schedule_date', '<=', $filters['to']);
                }
            });
        }

        return $query->orderBy('schedule_date', 'desc')->get();
    }

    /**
     * Get team attendance schedules (leader only).
     */
    public function getTeamAttendance(User $leader, array $filters): Collection
    {
        $teamMemberIds = User::where('team', $leader->team)->pluck('id');

        $query = SalesAttendanceSchedule::whereIn('user_id', $teamMemberIds)
            ->with(['user', 'contract']);

        // Apply filters
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->where(function ($q) use ($filters) {
                if (!empty($filters['from'])) {
                    $q->whereDate('schedule_date', '>=', $filters['from']);
                }
                if (!empty($filters['to'])) {
                    $q->whereDate('schedule_date', '<=', $filters['to']);
                }
            });
        }

        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->orderBy('schedule_date', 'desc')->get();
    }

    /**
     * Create an attendance schedule (leader only).
     */
    public function createSchedule(array $data, User $leader): SalesAttendanceSchedule
    {
        // Validate user is in same team
        $user = User::findOrFail($data['user_id']);
        if ($user->team !== $leader->team) {
            throw new \Exception('User must be in the same team as leader');
        }

        // Check for overlapping schedules
        $overlap = SalesAttendanceSchedule::where('user_id', $data['user_id'])
            ->whereDate('schedule_date', $data['schedule_date'])
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($q) use ($data) {
                        $q->where('start_time', '<=', $data['start_time'])
                          ->where('end_time', '>=', $data['end_time']);
                    });
            })
            ->exists();

        if ($overlap) {
            throw new \Exception('Schedule overlaps with existing schedule for this user');
        }

        return SalesAttendanceSchedule::create([
            'contract_id' => $data['contract_id'],
            'user_id' => $data['user_id'],
            'schedule_date' => $data['schedule_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'created_by' => $leader->id,
        ]);
    }

    /**
     * Update an attendance schedule (leader only; schedule must be for a team member).
     */
    public function updateSchedule(int $scheduleId, array $data, User $leader): SalesAttendanceSchedule
    {
        $schedule = SalesAttendanceSchedule::findOrFail($scheduleId);

        $teamMemberIds = User::where('team', $leader->team)->pluck('id');
        if (!$teamMemberIds->contains($schedule->user_id)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to update this schedule');
        }

        if (isset($data['user_id'])) {
            $user = User::findOrFail($data['user_id']);
            if ($user->team !== $leader->team) {
                throw new \Exception('User must be in the same team as leader');
            }
        }

        $schedule->update(array_filter($data, fn ($v) => $v !== null));

        return $schedule->fresh(['contract', 'user']);
    }

    /**
     * Delete an attendance schedule (leader only; schedule must be for a team member).
     */
    public function deleteSchedule(int $scheduleId, User $leader): void
    {
        $schedule = SalesAttendanceSchedule::findOrFail($scheduleId);

        $teamMemberIds = User::where('team', $leader->team)->pluck('id');
        if (!$teamMemberIds->contains($schedule->user_id)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to delete this schedule');
        }

        $schedule->delete();
    }

    /**
     * Alias for getMyAttendance (used by controller).
     */
    public function getMySchedules(array $filters, User $user): Collection
    {
        return $this->getMyAttendance($user, $filters);
    }

    /**
     * Alias for getTeamAttendance (used by controller).
     */
    public function getTeamSchedules(array $filters, User $leader): Collection
    {
        return $this->getTeamAttendance($leader, $filters);
    }
}
