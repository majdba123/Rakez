<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\StoreKnowledgeRequest;
use App\Http\Requests\AI\UpdateKnowledgeRequest;
use App\Models\AssistantKnowledgeEntry;
use App\Services\AI\AssistantKnowledgeEntryService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantKnowledgeController extends Controller
{
    public function __construct(
        protected AssistantKnowledgeEntryService $knowledgeService
    ) {}

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

        // Filter by is_active (only when explicitly provided — avoid has() matching empty values)
        if ($request->filled('is_active')) {
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
        $page = max(1, (int) $request->query('page', 1));

        $ordered = $query->orderBy('priority', 'asc')
            ->orderBy('updated_at', 'desc');
        $total = (clone $ordered)->count();
        $rows = (clone $ordered)->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $rows->all(),
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => $rows->count(),
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
                    'has_more_pages' => ($page * $perPage) < $total,
                ],
            ],
        ]);
    }

    /**
     * Store a new knowledge entry.
     */
    public function store(StoreKnowledgeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['priority'] = $data['priority'] ?? 100;

        $entry = $this->knowledgeService->create($data, $request->user());

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

        $entry = $this->knowledgeService->update($entry, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Knowledge entry updated successfully.',
            'data' => $entry,
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

        $this->knowledgeService->delete($entry);

        return response()->json([
            'success' => true,
            'message' => 'Knowledge entry deleted successfully.',
        ]);
    }
}

