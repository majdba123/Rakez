<?php

namespace App\Services;

use App\Models\ExclusiveProjectRequest;
use App\Models\Contract;
use App\Models\User;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use App\Events\AdminNotificationEvent;
use App\Events\UserNotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class ExclusiveProjectService
{
    /**
     * Create a new exclusive project request.
     */
    public function createRequest(array $data, User $user): ExclusiveProjectRequest
    {
        DB::beginTransaction();

        try {
            $request = ExclusiveProjectRequest::create([
                'requested_by' => $user->id,
                'project_name' => $data['project_name'],
                'developer_name' => $data['developer_name'],
                'developer_contact' => $data['developer_contact'],
                'project_description' => $data['project_description'] ?? null,
                'estimated_units' => $data['estimated_units'] ?? null,
                'location_city' => $data['location_city'],
                'location_district' => $data['location_district'] ?? null,
                'status' => 'pending',
            ]);

            DB::commit();

            // Notify project management managers
            $this->notifyManagers($request);

            return $request->fresh(['requestedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get exclusive project requests with filters and pagination.
     */
    public function getRequests(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ExclusiveProjectRequest::with(['requestedBy', 'approvedBy', 'contract']);

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by requested_by
        if (isset($filters['requested_by'])) {
            $query->where('requested_by', $filters['requested_by']);
        }

        // Filter by city
        if (isset($filters['location_city'])) {
            $query->where('location_city', $filters['location_city']);
        }

        // Default ordering (newest first)
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get a single exclusive project request by ID.
     */
    public function getRequest(int $id): ExclusiveProjectRequest
    {
        return ExclusiveProjectRequest::with(['requestedBy', 'approvedBy', 'contract'])
            ->findOrFail($id);
    }

    /**
     * Approve an exclusive project request (PM Manager only).
     */
    public function approveRequest(int $id, User $manager): ExclusiveProjectRequest
    {
        DB::beginTransaction();

        try {
            $request = ExclusiveProjectRequest::findOrFail($id);

            // Verify request is pending
            if (!$request->isPending()) {
                throw new Exception('Request is not pending');
            }

            // Approve the request
            $request->approve($manager);

            DB::commit();

            // Notify the requester
            $this->notifyRequesterApproval($request);

            return $request->fresh(['requestedBy', 'approvedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject an exclusive project request (PM Manager only).
     */
    public function rejectRequest(int $id, string $reason, User $manager): ExclusiveProjectRequest
    {
        DB::beginTransaction();

        try {
            $request = ExclusiveProjectRequest::findOrFail($id);

            // Verify request is pending
            if (!$request->isPending()) {
                throw new Exception('Request is not pending');
            }

            // Reject the request
            $request->reject($manager, $reason);

            DB::commit();

            // Notify the requester
            $this->notifyRequesterRejection($request);

            return $request->fresh(['requestedBy', 'approvedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete contract details for an approved exclusive project.
     */
    public function completeContract(int $id, array $contractData, User $user): ExclusiveProjectRequest
    {
        DB::beginTransaction();

        try {
            $request = ExclusiveProjectRequest::findOrFail($id);

            // Verify request is approved
            if (!$request->isApproved()) {
                throw new Exception('Request must be approved before completing contract');
            }

            // Create the contract
            $contract = Contract::create([
                'user_id' => $request->requested_by,
                'project_name' => $request->project_name,
                'developer_name' => $request->developer_name,
                'developer_number' => $request->developer_contact,
                'city' => $request->location_city,
                'district' => $request->location_district,
                'units' => $contractData['units'] ?? [],
                'status' => 'pending',
                'notes' => $contractData['notes'] ?? "Created from exclusive project request #{$request->id}",
            ]);

            // Mark request as completed
            $request->completeContract($contract);

            DB::commit();

            // Notify relevant parties
            $this->notifyContractCompletion($request, $contract);

            return $request->fresh(['requestedBy', 'approvedBy', 'contract']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Export contract as PDF.
     */
    public function exportContract(int $id): string
    {
        $request = ExclusiveProjectRequest::with(['requestedBy', 'approvedBy', 'contract'])
            ->findOrFail($id);

        // Verify contract is completed
        if (!$request->isContractCompleted() || !$request->contract) {
            throw new Exception('Contract is not completed yet');
        }

        // Generate PDF
        $pdf = Pdf::loadView('pdfs.exclusive_project_contract', [
            'request' => $request,
            'contract' => $request->contract,
        ]);

        // Save PDF to storage
        $filename = "exclusive_project_contract_{$request->id}_" . time() . ".pdf";
        $path = "contracts/exclusive/{$filename}";
        Storage::put($path, $pdf->output());

        // Update request with PDF path
        $request->update(['contract_pdf_path' => $path]);

        return $path;
    }

    /**
     * Notify project management managers about new request.
     */
    protected function notifyManagers(ExclusiveProjectRequest $request): void
    {
        // Get all project management managers
        $managers = User::where('type', 'project_management')
            ->where('is_manager', true)
            ->get();

        foreach ($managers as $manager) {
            AdminNotification::create([
                'user_id' => $manager->id,
                'type' => 'exclusive_project_request',
                'title' => 'New Exclusive Project Request',
                'message' => "New exclusive project request from {$request->requestedBy->name}: {$request->project_name}",
                'data' => [
                    'request_id' => $request->id,
                    'project_name' => $request->project_name,
                    'requested_by' => $request->requestedBy->name,
                ],
                'status' => 'pending',
            ]);

            event(new AdminNotificationEvent(
                "New exclusive project request: {$request->project_name}"
            ));
        }
    }

    /**
     * Notify requester about approval.
     */
    protected function notifyRequesterApproval(ExclusiveProjectRequest $request): void
    {
        UserNotification::create([
            'user_id' => $request->requested_by,
            'type' => 'exclusive_project_approved',
            'title' => 'Exclusive Project Request Approved',
            'message' => "Your exclusive project request '{$request->project_name}' has been approved",
            'data' => [
                'request_id' => $request->id,
                'project_name' => $request->project_name,
                'approved_by' => $request->approvedBy->name,
            ],
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent(
            $request->requested_by,
            'Exclusive Project Approved',
            "Your request '{$request->project_name}' has been approved"
        ));
    }

    /**
     * Notify requester about rejection.
     */
    protected function notifyRequesterRejection(ExclusiveProjectRequest $request): void
    {
        UserNotification::create([
            'user_id' => $request->requested_by,
            'type' => 'exclusive_project_rejected',
            'title' => 'Exclusive Project Request Rejected',
            'message' => "Your exclusive project request '{$request->project_name}' has been rejected",
            'data' => [
                'request_id' => $request->id,
                'project_name' => $request->project_name,
                'rejection_reason' => $request->rejection_reason,
            ],
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent(
            $request->requested_by,
            'Exclusive Project Rejected',
            "Your request '{$request->project_name}' has been rejected"
        ));
    }

    /**
     * Notify about contract completion.
     */
    protected function notifyContractCompletion(ExclusiveProjectRequest $request, Contract $contract): void
    {
        UserNotification::create([
            'user_id' => $request->requested_by,
            'type' => 'exclusive_project_contract_completed',
            'title' => 'Exclusive Project Contract Completed',
            'message' => "Contract for exclusive project '{$request->project_name}' has been completed",
            'data' => [
                'request_id' => $request->id,
                'contract_id' => $contract->id,
                'project_name' => $request->project_name,
            ],
            'status' => 'pending',
        ]);

        event(new UserNotificationEvent(
            $request->requested_by,
            'Contract Completed',
            "Contract for '{$request->project_name}' is ready"
        ));
    }
}
