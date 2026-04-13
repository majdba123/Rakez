<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\Skills\SkillCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSkillCatalogController extends Controller
{
    public function __construct(
        private readonly SkillCatalogService $catalogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'section' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        if (! $user->can('use-ai-assistant')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ], 403);
        }

        $payload = $this->catalogService->catalogForUser(
            $user,
            $request->input('section'),
        );

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
