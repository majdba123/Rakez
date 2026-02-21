<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExclusiveProject\StoreExclusiveProjectRequest;
use App\Http\Requests\ExclusiveProject\ApproveExclusiveProjectRequest;
use App\Http\Requests\ExclusiveProject\CompleteExclusiveContractRequest;
use App\Http\Resources\ExclusiveProject\ExclusiveProjectRequestResource;
use App\Http\Responses\ApiResponse;
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

            // My Requests: filter by current user when requested_by=me
            if ($request->query('requested_by') === 'me' || $request->query('my_requests')) {
                $filters['requested_by'] = $request->user()->id;
            }

            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);

            $perPage = ApiResponse::getPerPage($request, 15, 100);
            $requests = $this->exclusiveProjectService->getRequests($filters, $perPage);

            $data = ExclusiveProjectRequestResource::collection($requests->items())->resolve();
            return ApiResponse::success($data, 'تمت العملية بنجاح', 200, [
                'pagination' => [
                    'total' => $requests->total(),
                    'count' => $requests->count(),
                    'per_page' => $requests->perPage(),
                    'current_page' => $requests->currentPage(),
                    'total_pages' => $requests->lastPage(),
                    'has_more_pages' => $requests->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve exclusive project requests: ' . $e->getMessage());
        }
    }

    /**
     * Get a single exclusive project request.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $request = $this->exclusiveProjectService->getRequest($id);

            return ApiResponse::success(new ExclusiveProjectRequestResource($request));
        } catch (\Exception $e) {
            return ApiResponse::notFound('Exclusive project request not found: ' . $e->getMessage());
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

            return ApiResponse::created($exclusiveRequest, 'Exclusive project request created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create exclusive project request: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Approve an exclusive project request (PM Manager only).
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        try {
            $exclusiveRequest = $this->exclusiveProjectService->approveRequest($id, $request->user());

            return ApiResponse::success($exclusiveRequest, 'Exclusive project request approved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to approve exclusive project request: ' . $e->getMessage(), 400);
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

            return ApiResponse::success($exclusiveRequest, 'Exclusive project request rejected successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to reject exclusive project request: ' . $e->getMessage(), 400);
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

            return ApiResponse::success($exclusiveRequest, 'Contract completed successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to complete contract: ' . $e->getMessage(), 400);
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
            return ApiResponse::error('Failed to export contract: ' . $e->getMessage(), 400);
        }
    }
}
