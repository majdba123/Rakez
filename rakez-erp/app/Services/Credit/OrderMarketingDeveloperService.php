<?php

namespace App\Services\Credit;

use App\Models\OrderMarketingDeveloper;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderMarketingDeveloperService
{
    public function list(int $perPage): LengthAwarePaginator
    {
        return OrderMarketingDeveloper::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage);
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

    /**
     * @param  array<string, mixed>  $data  Validated fields only (developer_name, developer_number, description, location)
     */
    public function update(OrderMarketingDeveloper $row, array $data, User $user): OrderMarketingDeveloper
    {
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

    public function delete(int $id): void
    {
        OrderMarketingDeveloper::findOrFail($id)->delete();
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
