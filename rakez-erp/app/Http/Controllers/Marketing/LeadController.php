<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreLeadRequest;
use App\Http\Requests\Marketing\UpdateLeadRequest;
use App\Http\Requests\Marketing\ConvertLeadRequest;
use App\Http\Requests\Marketing\AssignLeadRequest;
use App\Models\Lead;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $perPage = ApiResponse::getPerPage($request);
        $leads = Lead::with(['project', 'assignedTo'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::paginated($leads, 'تم جلب قائمة العملاء المحتملين بنجاح');
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'data' => $lead
        ], 201);
    }

    public function update(int $leadId, UpdateLeadRequest $request): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);
        $this->authorize('update', $lead);
        $lead->update($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data' => $lead
        ]);
    }

    public function convert(int $leadId, ConvertLeadRequest $request): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);
        $this->authorize('update', $lead);

        $lead->update([
            'status' => 'converted'
        ]);

        // Optionally create related records (reservation, customer, etc.)
        // This can be extended based on business requirements

        return response()->json([
            'success' => true,
            'message' => 'Lead converted successfully',
            'data' => $lead->fresh(['project', 'assignedTo'])
        ]);
    }

    public function assign(int $leadId, AssignLeadRequest $request): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);
        $this->authorize('update', $lead);

        $lead->update([
            'assigned_to' => $request->input('assigned_to')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead assigned successfully',
            'data' => $lead->fresh(['project', 'assignedTo'])
        ]);
    }
}
