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
     * @param  array{processed_by?: int|string|null}  $filters
     */
    public function list(int $perPage, User $user, array $filters = []): LengthAwarePaginator
    {
        $query = OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name']);

        if ($this->canViewAllOrderMarketingDevelopers($user)) {
            $processorId = isset($filters['processed_by']) ? (int) $filters['processed_by'] : 0;
            if ($processorId > 0) {
                $query->where(function (Builder $q) use ($processorId): void {
                    $q->where('created_by', $processorId)
                        ->orWhere('updated_by', $processorId);
                });
            }
        } else {
            $query->where('created_by', (int) $user->id);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * Admin or credit manager: full list + optional processed_by filter.
     * Credit employee: only rows they created.
     */
    public function canViewAllOrderMarketingDevelopers(User $user): bool
    {
        if ($user->hasRole('admin') || $user->isAdmin()) {
            return true;
        }

        return $user->type === 'credit' && $user->isManager();
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
     * @param  array<string, mixed>  $data  Validated fields only (developer_name, developer_number, description, location)
     */
    public function update(OrderMarketingDeveloper $row, array $data, User $user): OrderMarketingDeveloper
    {
        $this->assertUserCanAccessOrder($row, $user);

        if (!$row->isPending()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'لا يمكن التعديل إلا عندما تكون الحالة قيد الانتظار',
            ], 422));
        }

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
        $row->delete();
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
