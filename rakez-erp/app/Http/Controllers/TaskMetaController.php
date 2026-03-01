<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskMetaController extends Controller
{
    /**
     * List available sections (user types) that can receive tasks.
     * GET /api/tasks/sections
     */
    public function sections(Request $request): JsonResponse
    {
        $types = User::query()
            ->whereNotNull('type')
            ->where('is_active', true)
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        $data = $types->map(fn (string $type) => [
            'value' => $type,
            'label' => $type,
        ])->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * List users in a given section (by user.type).
     * GET /api/tasks/sections/{section}/users
     */
    public function usersBySection(string $section): JsonResponse
    {
        $users = User::query()
            ->where('type', $section)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'team_id']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}

