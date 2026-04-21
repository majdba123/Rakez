<?php

namespace App\Services\Credit;

use App\Models\OrderMarketingDeveloper;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderMarketingDeveloperService
{
    /**
     * List orders with optional filters.
     *
     * - Credit employee (not admin, not credit manager): only rows they created (`created_by` = user). Filters apply within that set.
     * - Admin or credit department manager: full table; optional filters narrow results; empty filters = all rows.
     * - `processed_by` (creator or last updater) applies only for admin / credit manager; ignored for other users.
     *
     * @param  array<string, mixed>  $filters  Validated query filters from IndexOrderMarketingDeveloperRequest
     */
    public function list(int $perPage, User $user, array $filters = []): LengthAwarePaginator
    {
        $query = OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name']);

        if ($this->isAdminOrCreditDepartmentManager($user)) {
            $processorId = isset($filters['processed_by']) ? (int) $filters['processed_by'] : 0;
            if ($processorId > 0) {
                $query->where(function (Builder $q) use ($processorId): void {
                    $q->where('created_by', $processorId)
                        ->orWhere('updated_by', $processorId);
                });
            }
        } else {
            // Credit account (and any non-manager): only own orders
            $query->where('created_by', (int) $user->id);
        }

        $this->applyListColumnFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Admin or credit department manager: unrestricted list + filters; no filters = all records.
     */
    public function canViewAllOrderMarketingDevelopers(User $user): bool
    {
        return $this->isAdminOrCreditDepartmentManager($user);
    }

    protected function isAdminOrCreditDepartmentManager(User $user): bool
    {
        if ($user->hasRole('admin') || $user->isAdmin()) {
            return true;
        }

        return $user->type === 'credit' && $user->isManager();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyListColumnFilters(Builder $query, array $filters): void
    {
        unset($filters['processed_by']);

        if (!empty($filters['id'])) {
            $query->where('id', (int) $filters['id']);
        }

        if (!empty($filters['developer_name'])) {
            $query->where('developer_name', 'like', $this->likePattern((string) $filters['developer_name']));
        }

        if (!empty($filters['developer_number'])) {
            $query->where('developer_number', 'like', $this->likePattern((string) $filters['developer_number']));
        }

        if (!empty($filters['description'])) {
            $query->where('description', 'like', $this->likePattern((string) $filters['description']));
        }

        if (!empty($filters['location'])) {
            $query->where('location', 'like', $this->likePattern((string) $filters['location']));
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (!empty($filters['updated_by'])) {
            $query->where('updated_by', (int) $filters['updated_by']);
        }

        if (!empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        if (!empty($filters['updated_from'])) {
            $query->whereDate('updated_at', '>=', $filters['updated_from']);
        }

        if (!empty($filters['updated_to'])) {
            $query->whereDate('updated_at', '<=', $filters['updated_to']);
        }
    }

    protected function likePattern(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    public function create(array $data, User $user): OrderMarketingDeveloper
    {
        return OrderMarketingDeveloper::create([
            'developer_name' => $data['developer_name'],
            'developer_number' => $data['developer_number'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => OrderMarketingDeveloper::STATUS_PENDING,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function find(int $id): OrderMarketingDeveloper
    {
        return OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->findOrFail($id);
    }

    public function findForUser(int $id, User $user): OrderMarketingDeveloper
    {
        $row = $this->find($id);
        $this->assertUserCanAccessOrder($row, $user);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data  developer_* fields, or `status` (admin only: approved|rejected from pending)
     */
    public function update(OrderMarketingDeveloper $row, array $data, User $user): OrderMarketingDeveloper
    {
        $this->assertUserCanAccessOrder($row, $user);

        if (array_key_exists('status', $data)) {
            if (!$user->hasRole('admin') && !$user->isAdmin()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'هذه العملية للمسؤول فقط',
                ], 403));
            }

            $status = (string) $data['status'];
            $allowed = [OrderMarketingDeveloper::STATUS_APPROVED, OrderMarketingDeveloper::STATUS_REJECTED];
            if (!in_array($status, $allowed, true)) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'الحالة غير صالحة',
                ], 422));
            }

            if (!$row->isPending()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'يمكن تغيير الحالة من قيد الانتظار فقط إلى موافق أو مرفوض',
                ], 422));
            }

            $row->update([
                'status' => $status,
                'updated_by' => $user->id,
            ]);

            return $row->fresh(['createdBy:id,name', 'updatedBy:id,name']);
        }

        $this->assertCanModifyOrDelete($row, 'تعديل');

        $payload = array_merge(
            array_intersect_key($data, array_flip(['developer_name', 'developer_number', 'description', 'location'])),
            ['updated_by' => $user->id]
        );

        $row->update($payload);

        return $row->fresh(['createdBy:id,name', 'updatedBy:id,name']);
    }

    public function delete(int $id, User $user): void
    {
        $row = OrderMarketingDeveloper::findOrFail($id);
        $this->assertUserCanAccessOrder($row, $user);
        $this->assertCanModifyOrDelete($row, 'حذف');
        $row->delete();
    }

    /**
     * Content update/delete: allowed when pending or rejected; not when approved.
     */
    protected function assertCanModifyOrDelete(OrderMarketingDeveloper $row, string $actionLabelAr): void
    {
        if ($row->isApproved()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'لا يمكن '.$actionLabelAr.' السجل بعد الموافقة عليه',
            ], 422));
        }

        if (!$row->isPending() && !$row->isRejected()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'لا يمكن '.$actionLabelAr.' إلا عندما تكون الحالة قيد الانتظار أو مرفوض',
            ], 422));
        }
    }

    protected function assertUserCanAccessOrder(OrderMarketingDeveloper $row, User $user): void
    {
        if ($this->canViewAllOrderMarketingDevelopers($user)) {
            return;
        }

        if ((int) $row->created_by !== (int) $user->id) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض أو تعديل هذا السجل',
            ], 403));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(OrderMarketingDeveloper $row): array
    {
        return [
            'id' => $row->id,
            'developer_name' => $row->developer_name,
            'developer_number' => $row->developer_number,
            'description' => $row->description,
            'location' => $row->location,
            'status' => $row->status,
            'created_by' => $row->createdBy ? [
                'id' => $row->createdBy->id,
                'name' => $row->createdBy->name,
            ] : null,
            'updated_by' => $row->updatedBy ? [
                'id' => $row->updatedBy->id,
                'name' => $row->updatedBy->name,
            ] : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
