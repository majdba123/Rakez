<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExclusiveProject\StoreExclusiveProjectRequest;
use App\Http\Requests\ExclusiveProject\ApproveExclusiveProjectRequest;
use App\Http\Requests\ExclusiveProject\CompleteExclusiveContractRequest;
use App\Services\ExclusiveProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ExclusiveProjectController extends Controller
{
    public function __construct(
        private ExclusiveProjectService $exclusiveProjectService
    ) {}

    /**
     * Get exclusive project requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'requested_by' => $request->query('requested_by'),
                'location_city' => $request->query('location_city'),
            ];

            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);

            $perPage = $request->query('per_page', 15);
            $requests = $this->exclusiveProjectService->getRequests($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $requests->items(),
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exclusive project requests: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single exclusive project request.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $request = $this->exclusiveProjectService->getRequest($id);

            return response()->json([
                'success' => true,
                'data' => $request,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exclusive project request not found: ' . $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create a new exclusive project request.
     */
    public function store(StoreExclusiveProjectRequest $request): JsonResponse
    {
        try {
            $exclusiveRequest = $this->exclusiveProjectService->createRequest(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Exclusive project request created successfully',
                'data' => $exclusiveRequest,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create exclusive project request: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Approve an exclusive project request (PM Manager only).
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        try {
            $exclusiveRequest = $this->exclusiveProjectService->approveRequest($id, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Exclusive project request approved successfully',
                'data' => $exclusiveRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve exclusive project request: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reject an exclusive project request (PM Manager only).
     */
    public function reject(int $id, ApproveExclusiveProjectRequest $request): JsonResponse
    {
        try {
            $exclusiveRequest = $this->exclusiveProjectService->rejectRequest(
                $id,
                $request->input('rejection_reason'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Exclusive project request rejected successfully',
                'data' => $exclusiveRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject exclusive project request: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Complete contract details for an approved exclusive project.
     */
    public function completeContract(int $id, CompleteExclusiveContractRequest $request): JsonResponse
    {
        try {
            $exclusiveRequest = $this->exclusiveProjectService->completeContract(
                $id,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Contract completed successfully',
                'data' => $exclusiveRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete contract: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Export contract as PDF.
     */
    public function exportContract(int $id): mixed
    {
        try {
            $path = $this->exclusiveProjectService->exportContract($id);

            // Return file download response
            return response()->download(
                Storage::path($path),
                basename($path),
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export contract: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
