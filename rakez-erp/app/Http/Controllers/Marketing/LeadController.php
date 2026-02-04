<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreLeadRequest;
use App\Http\Requests\Marketing\UpdateLeadRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        return response()->json([
            'success' => true,
            'data' => Lead::with(['project', 'assignedTo'])->get()
        ]);
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
}
