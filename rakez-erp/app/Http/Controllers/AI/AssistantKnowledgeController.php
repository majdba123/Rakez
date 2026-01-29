<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreKnowledgeRequest;
use App\Http\Requests\AI\UpdateKnowledgeRequest;
use App\Models\AssistantKnowledgeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantKnowledgeController extends Controller
{
    /**
     * List knowledge entries with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssistantKnowledgeEntry::query();

        // Filter by module
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        // Filter by page_key
        if ($request->filled('page_key')) {
            $query->where('page_key', $request->input('page_key'));
        }

        // Filter by language
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Search in title
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_md', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $page = max((int) $request->input('page', 1), 1);
        
        // Get total count first
        $total = (clone $query)->count();
        
        // Then get paginated items
        $items = $query->orderBy('priority', 'asc')
            ->orderBy('updated_at', 'desc')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items->toArray(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Store a new knowledge entry.
     */
    public function store(StoreKnowledgeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['priority'] = $data['priority'] ?? 100;

        $entry = AssistantKnowledgeEntry::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Knowledge entry created successfully.',
            'data' => $entry,
        ], 201);
    }

    /**
     * Update an existing knowledge entry.
     */
    public function update(UpdateKnowledgeRequest $request, int $id): JsonResponse
    {
        $entry = AssistantKnowledgeEntry::find($id);

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Knowledge entry not found.',
            ], 404);
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $entry->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Knowledge entry updated successfully.',
            'data' => $entry->fresh(),
        ]);
    }

    /**
     * Delete a knowledge entry.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = AssistantKnowledgeEntry::find($id);

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Knowledge entry not found.',
            ], 404);
        }

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Knowledge entry deleted successfully.',
        ]);
    }
}

