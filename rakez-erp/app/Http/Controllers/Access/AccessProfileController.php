<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\FrontendAccessProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessProfileController extends Controller
{
    public function __construct(
        protected FrontendAccessProfileService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->build($user),
        ]);
    }
}
