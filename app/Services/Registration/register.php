<?php

namespace App\Services\Registration;

use App\Constants\Pagination;
use App\Constants\UserType;
use App\Events\AdminNotificationEvent;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

            $optional = [
                'team_id',
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
                    $userData[$key] = $data[$key];
                }
            }

            if (isset($data['team']) && !isset($data['team_id'])) {
                $userData['team_id'] = $data['team'];
            }

            $typeNames = UserType::legacyMap();
            if (!isset($data['type']) || !array_key_exists($data['type'], $typeNames)) {
                throw new \InvalidArgumentException('نوع المستخدم غير صالح');
            }
            $userData['type'] = $typeNames[$data['type']];

            $user = User::create($userData);

            if (isset($data['role'])) {
                if (!Role::where('name', $data['role'])->exists()) {
                    throw new \InvalidArgumentException("Role '{$data['role']}' does not exist.");
                }
                $user->syncRoles([$data['role']]);
            } else {
                $user->syncRolesFromType();
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            AdminNotification::createForNewEmployee($user);
            DB::commit();

            event(new AdminNotificationEvent('New employee added with ID: ' . $user->id));

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * List employees with filtering and pagination.
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listEmployees(array $filters = [])
    {
        $query = User::query();

        $select = [
            'id', 'name', 'email', 'phone', 'type', 'is_manager', 'team_id', 'identity_number',
            'birthday', 'date_of_works', 'contract_type',
            'iban', 'salary', 'marital_status', 'created_at', 'updated_at'
        ];
        $query->select($select);

        $typeNames = UserType::legacyMap();
        if (isset($filters['type'])) {
            $typeFilter = $filters['type'];
            if (is_numeric($typeFilter) && array_key_exists((int) $typeFilter, $typeNames)) {
                $typeFilter = $typeNames[(int) $typeFilter];
            }
            $query->where('type', $typeFilter);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        $userTable = (new User)->getTable();
        if (isset($filters['status']) && Schema::hasColumn($userTable, 'deleted_at')) {
            if ($filters['status'] === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($filters['status'] === 'deleted' && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(User::class), true)) {
                $query->onlyTrashed();
            }
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        $perPage = isset($filters['per_page']) ? (int) min(Pagination::MAX_PER_PAGE, max(1, (int) $filters['per_page'])) : Pagination::DEFAULT_PER_PAGE;
        return $query->paginate($perPage);
    }

    /**
     * Show specific employee details.
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
     * Update employee information.
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

            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            if (isset($data['phone'])) {
                $updateData['phone'] = $data['phone'];
            }

            $profileFields = [
                'team_id', 'identity_number', 'birthday', 'date_of_works', 'contract_type',
                'iban', 'salary', 'marital_status', 'is_manager',
            ];
            foreach ($profileFields as $pf) {
                if (array_key_exists($pf, $data)) {
                    $updateData[$pf] = $data[$pf];
                }
            }

            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (isset($data['type'])) {
                $typeNames = UserType::legacyMap();
                if (!array_key_exists($data['type'], $typeNames)) {
                    throw new \InvalidArgumentException('نوع المستخدم غير صالح');
                }
                $updateData['type'] = $typeNames[$data['type']];
            }

            $user->update($updateData);

            if (isset($data['role'])) {
                if (!Role::where('name', $data['role'])->exists()) {
                    throw new \InvalidArgumentException("Role '{$data['role']}' does not exist.");
                }
                $user->syncRoles([$data['role']]);
            } elseif (isset($data['type']) || isset($data['is_manager'])) {
                $user->syncRolesFromType();
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete employee (soft delete if supported, else hard delete).
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
     * Restore soft deleted employee.
     *
     * @param int $id
     * @return bool
     */
    public function restoreEmployee($id): bool
    {
        if (!in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(User::class), true)) {
            throw new \Exception('User model does not use soft deletes');
        }
        $user = User::onlyTrashed()->find($id);
        if (!$user) {
            throw new \Exception('Deleted employee not found');
        }
        return $user->restore();
    }

    /**
     * Get employee statistics.
     *
     * @return array
     */
    public function getEmployeeStats(): array
    {
        return Cache::remember('employee_stats_v1', 30, function () {
            $totalEmployees = User::count();
            $activeEmployees = Schema::hasColumn((new User)->getTable(), 'deleted_at')
                ? User::whereNull('deleted_at')->count()
                : $totalEmployees;
            $userTable = (new User)->getTable();
            $deletedEmployees = (Schema::hasColumn($userTable, 'deleted_at') && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(User::class), true))
                ? User::onlyTrashed()->count()
                : 0;

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
     * Generate random password.
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
            $randomPassword .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }
}
