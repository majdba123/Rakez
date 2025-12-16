<?php

namespace App\Services\Contract;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Container\Attributes\Auth;

class ContractService
{
    /**
     * Get all contracts with filters
     */
    public function getContracts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::query();

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city
            if (isset($filters['city']) && !empty($filters['city'])) {
                $query->where('city', $filters['city']);
            }

            // Filter by district
            if (isset($filters['district']) && !empty($filters['district'])) {
                $query->where('district', $filters['district']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('project_name', 'like', '%' . $filters['project_name'] . '%');
            }

            // Sort by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contracts: ' . $e->getMessage());
        }
    }

    /**
     * Store a new contract
     */
    public function storeContract(array $data): Contract
    {
        DB::beginTransaction();
        try {
            // Set status to pending by default
            $data['status'] = 'pending';
            $data['user_id'] = auth()->user()->id;
            // Calculate total units value if not provided
            if (isset($data['units_count']) && isset($data['average_unit_price'])) {
                $data['total_units_value'] = $data['units_count'] * $data['average_unit_price'];
            }

            $contract = Contract::create($data);

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create contract: ' . $e->getMessage());
        }
    }

    /**
     * Get a single contract by ID (with user authorization)
     */
    public function getContractById(int $id, int $userId = null): Contract
    {
        try {
            $contract = Contract::findOrFail($id);

            // Check if user owns the contract
            if ($userId && $contract->user_id !== $userId) {
                throw new Exception('Unauthorized to view this contract.');
            }

            return $contract;
        } catch (Exception $e) {
            throw new Exception('Contract not found or unauthorized: ' . $e->getMessage());
        }
    }

    /**
     * Update a contract (only when status is pending and user owns it)
     */
    public function updateContract(int $id, array $data, int $userId = null): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($id);

            // Check if user owns the contract
            if ($userId && $contract->user_id !== $userId) {
                throw new Exception('Unauthorized to update this contract.');
            }

            // Check if contract is pending
            if (!$contract->isPending()) {
                throw new Exception('Contract can only be updated when status is pending.');
            }

            // Prevent status update during update operation
            unset($data['status']);

            // Recalculate total units value if units or price changed
            if (isset($data['units_count']) || isset($data['average_unit_price'])) {
                $unitsCount = $data['units_count'] ?? $contract->units_count;
                $averagePrice = $data['average_unit_price'] ?? $contract->average_unit_price;
                $data['total_units_value'] = $unitsCount * $averagePrice;
            }

            $contract->update($data);

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update contract: ' . $e->getMessage());
        }
    }

    /**
     * Delete a contract (only when status is pending and user owns it)
     */
    public function deleteContract(int $id, int $userId = null): bool
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($id);

            // Check if user owns the contract
            if ($userId && $contract->user_id !== $userId) {
                throw new Exception('Unauthorized to delete this contract.');
            }

            // Check if contract is pending before deletion
            if (!$contract->isPending()) {
                throw new Exception('Only pending contracts can be deleted.');
            }

            $contract->delete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete contract: ' . $e->getMessage());
        }
    }

    /**
     * Get all contracts for admin with filters
     */
    public function getContractsForAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Contract::query();

            // Filter by status
            if (isset($filters['status']) && !empty($filters['status'])) {
                $query->byStatus($filters['status']);
            }

            // Filter by user
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $query->byUser($filters['user_id']);
            }

            // Filter by city
            if (isset($filters['city']) && !empty($filters['city'])) {
                $query->where('city', $filters['city']);
            }

            // Filter by district
            if (isset($filters['district']) && !empty($filters['district'])) {
                $query->where('district', $filters['district']);
            }

            // Filter by project name (search)
            if (isset($filters['project_name']) && !empty($filters['project_name'])) {
                $query->where('project_name', 'like', '%' . $filters['project_name'] . '%');
            }

            // Sort by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch contracts: ' . $e->getMessage());
        }
    }

    /**
     * Update contract status (admin only)
     */
    public function updateContractStatus(int $id, string $status): Contract
    {
        DB::beginTransaction();
        try {
            $contract = Contract::findOrFail($id);

            // Check if status is valid
            $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
            }

            // Check if contract is pending before changing status
            if (!$contract->isPending()) {
                throw new Exception('Only pending contracts can have their status changed.');
            }

            $contract->update(['status' => $status]);

            DB::commit();
            return $contract;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update contract status: ' . $e->getMessage());
        }
    }
}
