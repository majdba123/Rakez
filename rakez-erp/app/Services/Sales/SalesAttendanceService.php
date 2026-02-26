<?php

namespace App\Services\Sales;

use App\Models\Contract;
use App\Models\SalesAttendanceSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        if (!$leader->team_id) {
            return collect([]);
        }
        $teamMemberIds = User::where('team_id', $leader->team_id)->pluck('id');

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
        if (!$leader->team_id || $user->team_id !== $leader->team_id) {
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

    /**
     * Get project attendance overview for a given date.
     * Returns all team members with their attendance status on that date for the project.
     * Includes server_date, server_time, and day_name_ar so the UI can 100% match backend dates.
     */
    public function getProjectAttendanceOverview(int $contractId, string $date, User $leader): array
    {
        $contract = Contract::findOrFail($contractId);

        $teamMembers = $this->getLeaderTeamMembers($leader);
        $memberIds = $teamMembers->pluck('id');

        $existingSchedules = SalesAttendanceSchedule::where('contract_id', $contractId)
            ->whereDate('schedule_date', $date)
            ->whereIn('user_id', $memberIds)
            ->get()
            ->keyBy('user_id');

        $members = $teamMembers->map(function ($member) use ($existingSchedules) {
            $schedule = $existingSchedules->get($member->id);

            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'is_present' => $schedule !== null,
                'schedule' => $schedule ? [
                    'schedule_id' => $schedule->id,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ] : null,
            ];
        })->values()->all();

        $now = Carbon::now();
        $requestedDate = Carbon::parse($date);

        return [
            'project' => [
                'id' => $contract->id,
                'name' => $contract->project_name,
                'location' => trim(($contract->city ?? '') . ', ' . ($contract->district ?? ''), ', '),
            ],
            'date' => $date,
            'server_date' => $now->format('Y-m-d'),
            'server_time' => $now->format('H:i:s'),
            'day_name_ar' => $this->dayNameArabic($requestedDate),
            'members' => $members,
        ];
    }

    /**
     * Team members for the leader (same team_id, type sales, exclude leader).
     */
    private function getLeaderTeamMembers(User $leader): Collection
    {
        if (!$leader->team_id) {
            return collect([]);
        }
        return User::where('team_id', $leader->team_id)
            ->where('type', 'sales')
            ->where('id', '!=', $leader->id)
            ->select('id', 'name', 'email')
            ->get();
    }

    /**
     * Arabic day name for a date (for display in project schedule view).
     */
    private function dayNameArabic(Carbon $date): string
    {
        $days = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];
        return $days[$date->dayOfWeek] ?? '';
    }

    /**
     * Bulk save attendance schedules for a project on a given day.
     * Returns arrays of created, updated, and removed schedule changes for notifications.
     */
    public function bulkSaveSchedules(int $contractId, array $data, User $leader): array
    {
        $date = $data['date'];
        $schedules = $data['schedules'];
        $contract = Contract::findOrFail($contractId);

        $teamMemberIds = $this->getLeaderTeamMembers($leader)->pluck('id')->toArray();

        $created = [];
        $updated = [];
        $removed = [];

        DB::transaction(function () use ($contractId, $date, $schedules, $leader, $teamMemberIds, &$created, &$updated, &$removed) {
            foreach ($schedules as $entry) {
                $userId = $entry['user_id'];

                if (!in_array($userId, $teamMemberIds)) {
                    continue;
                }

                $existing = SalesAttendanceSchedule::where('contract_id', $contractId)
                    ->where('user_id', $userId)
                    ->whereDate('schedule_date', $date)
                    ->first();

                if ($entry['present']) {
                    $scheduleData = [
                        'contract_id' => $contractId,
                        'user_id' => $userId,
                        'schedule_date' => $date,
                        'start_time' => $entry['start_time'],
                        'end_time' => $entry['end_time'],
                        'created_by' => $leader->id,
                    ];

                    if ($existing) {
                        $existing->update([
                            'start_time' => $entry['start_time'],
                            'end_time' => $entry['end_time'],
                        ]);
                        $updated[] = [
                            'user_id' => $userId,
                            'start_time' => $entry['start_time'],
                            'end_time' => $entry['end_time'],
                        ];
                    } else {
                        SalesAttendanceSchedule::create($scheduleData);
                        $created[] = [
                            'user_id' => $userId,
                            'start_time' => $entry['start_time'],
                            'end_time' => $entry['end_time'],
                        ];
                    }
                } else {
                    if ($existing) {
                        $existing->delete();
                        $removed[] = ['user_id' => $userId];
                    }
                }
            }
        });

        return [
            'contract' => $contract,
            'date' => $date,
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }
}
