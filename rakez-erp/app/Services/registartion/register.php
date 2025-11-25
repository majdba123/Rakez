<?php

namespace App\Services\registartion;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            ];

            if (!isset($data['type']) || !array_key_exists($data['type'], $typeNames)) {
                throw new \InvalidArgumentException('نوع المستخدم غير صالح');
            }

            $userData['type'] = $typeNames[$data['type']];

            $user = User::create($userData);

            DB::commit();
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

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
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

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
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
        $user = User::find($id);

        if (!$user) {
            throw new \Exception('Employee not found');
        }

        return $user;
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
