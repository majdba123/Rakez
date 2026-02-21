<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreKnowledgeRequest;
use App\Http\Requests\AI\UpdateKnowledgeRequest;
use App\Models\AssistantKnowledgeEntry;
use App\Http\Responses\ApiResponse;
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

        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $items = $query->orderBy('priority', 'asc')
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        return ApiResponse::success($items->items(), 'تمت العملية بنجاح', 200, [
            'pagination' => [
                'total' => $items->total(),
                'count' => $items->count(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'total_pages' => $items->lastPage(),
                'has_more_pages' => $items->hasMorePages(),
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

        return ApiResponse::created($entry, 'Knowledge entry created successfully.');
    }

    /**
     * Update an existing knowledge entry.
     */
    public function update(UpdateKnowledgeRequest $request, int $id): JsonResponse
    {
        $entry = AssistantKnowledgeEntry::find($id);

        if (!$entry) {
            return ApiResponse::notFound('Knowledge entry not found.');
        }

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $entry->update($data);

        return ApiResponse::success($entry->fresh(), 'Knowledge entry updated successfully.');
    }

    /**
     * Delete a knowledge entry.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = AssistantKnowledgeEntry::find($id);

        if (!$entry) {
            return ApiResponse::notFound('Knowledge entry not found.');
        }

        $entry->delete();

        return ApiResponse::success(null, 'Knowledge entry deleted successfully.');
    }
}

