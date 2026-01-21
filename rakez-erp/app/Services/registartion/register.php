<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\AdminNotification;
use App\Events\AdminNotificationEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class register
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        DB::beginTransaction();

        try {
            if (isset($data['email'])) {
                $userData = [
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                ];
            } else {
                throw new \Exception('يجب أن تحتوي البيانات إما على البريد الإلكتروني أو رقم الهاتف.');
            }

            // Optional profile fields
            $optional = [
                // `team` input is team id (teams.id) -> store as users.team_id
                'team',
                'identity_number',
                'birthday',
                'date_of_works',
                'contract_type',
                'iban',
                'salary',
                'marital_status',
                'is_manager',
            ];

            foreach ($optional as $key) {
                if (isset($data[$key])) {
                    if ($key === 'team') {
                        $userData['team_id'] = $data[$key];
                    } else {
                        $userData[$key] = $data[$key];
                    }
                }
            }

            // Add user type
            $typeNames = [
                0 => 'marketing',
                1 => 'admin',
                2 => 'project_acquisition',
                3 => 'project_management',
                4 => 'editor',
                5 => 'sales',
                6 => 'accounting',
                7 => 'credit',
                8 => 'HR',
            ];

            if (!isset($data['type']) || !array_key_exists($data['type'], $typeNames)) {
                throw new \InvalidArgumentException('نوع المستخدم غير صالح');
            }

            $userData['type'] = $typeNames[$data['type']];

            $user = User::create($userData);

            // Save to admin_notifications table
            AdminNotification::createForNewEmployee($user);

            DB::commit();

            // Broadcast to admin channel (real-time)
            event(new AdminNotificationEvent('New employee added with ID: ' . $user->id));

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * List employees with filtering and pagination
     *
     * @param array $filters
     * @return mixed
     */
    public function listEmployees(array $filters = [])
    {
        $query = User::query();

        // Select only the columns used by the API resource to reduce payload
        $select = [
            'id', 'name', 'email', 'phone', 'type', 'is_manager', 'team_id', 'identity_number',
             'birthday', 'date_of_works', 'contract_type',
            'iban', 'salary', 'marital_status', 'created_at', 'updated_at'
        ];
        $query->select($select);

        // Map numeric type filter to stored type name to be tolerant of both forms
        $typeNames = [
            0 => 'marketing',
            1 => 'admin',
            2 => 'project_acquisition',
            3 => 'project_management',
            4 => 'editor',
            5 => 'sales',
            6 => 'accounting',
            7 => 'credit',
            8 => 'HR',
        ];
        // Filter by type
        if (isset($filters['type'])) {
            $typeFilter = $filters['type'];
            if (is_numeric($typeFilter) && array_key_exists((int)$typeFilter, $typeNames)) {
                $typeFilter = $typeNames[(int)$typeFilter];
            }
            $query->where('type', $typeFilter);
        }

        // Filter by name (search)
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Filter by status
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($filters['status'] === 'deleted') {
                $query->onlyTrashed();
            }
        }

        // Date range filter
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        // Sorting
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // Pagination (ensure reasonable maximum)
        $perPage = isset($filters['per_page']) ? (int) min(100, max(1, $filters['per_page'])) : 15;
        return $query->paginate($perPage);
    }

    /**
     * Show specific employee details
     *
     * @param int $id
     * @return User
     */
    public function showEmployee($id): User
    {
        try {
            return User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Employee not found');
        }
    }

    /**
     * Update employee information
     *
     * @param int $id
     * @param array $data
     * @return User
     */
    public function updateEmployee($id, array $data): User
    {
        DB::beginTransaction();

        try {
            $user = User::find($id);

            if (!$user) {
                throw new \Exception('Employee not found');
            }

            // Prepare update data
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];

            // Update optional profile fields
            $profileFields = [
                'team',
                'identity_number',
                'birthday',
                'date_of_works',
                'contract_type',
                'iban',
                'salary',
                'marital_status',
                'is_manager',
            ];

            foreach ($profileFields as $pf) {
                if (array_key_exists($pf, $data)) {
                    if ($pf === 'team') {
                        $updateData['team_id'] = $data[$pf];
                    } else {
                        $updateData[$pf] = $data[$pf];
                    }
                }
            }

            // Update password if provided
            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // Update type if provided
            if (isset($data['type'])) {
                $typeNames = [
                    0 => 'marketing',
                    1 => 'admin',
                    2 => 'project_acquisition',
                    3 => 'project_management',
                    4 => 'editor',
                    5 => 'sales',
                    6 => 'accounting',
                    7 => 'credit',
                    8 => 'HR',
                ];

                if (!array_key_exists($data['type'], $typeNames)) {
                    throw new \InvalidArgumentException('نوع المستخدم غير صالح');
                }

                $updateData['type'] = $typeNames[$data['type']];
            }

            $user->update($updateData);

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete employee (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function deleteEmployee($id): bool
    {
        $user = User::find($id);

        if (!$user) {
            throw new \Exception('Employee not found');
        }

        return $user->delete();
    }

    /**
     * Restore soft deleted employee
     *
     * @param int $id
     * @return bool
     */
    public function restoreEmployee($id): bool
    {
        $user = User::onlyTrashed()->find($id);

        if (!$user) {
            throw new \Exception('Deleted employee not found');
        }

        return $user->restore();
    }

    /**
     * Get employee statistics
     *
     * @return array
     */
    public function getEmployeeStats(): array
    {
        // Cache stats briefly to reduce DB load on frequent calls
        return Cache::remember('employee_stats_v1', 30, function () {
            $totalEmployees = User::count();
            $activeEmployees = User::whereNull('deleted_at')->count();
            $deletedEmployees = User::onlyTrashed()->count();

            $employeesByType = User::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type')
                ->toArray();

            return [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'deleted_employees' => $deletedEmployees,
                'employees_by_type' => $employeesByType,
            ];
        });
    }

    /**
     * Generate random password
     *
     * @param int $length
     * @return string
     */
    public function generateRandomPassword($length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomPassword = '';

        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomPassword;
    }

}
