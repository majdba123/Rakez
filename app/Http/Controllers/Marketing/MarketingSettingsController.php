<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\UpdateMarketingSettingRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketingSetting;
use Illuminate\Http\JsonResponse;

class MarketingSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', MarketingSetting::class);

        return ApiResponse::success(MarketingSetting::all());
    }

    public function update(string $key, UpdateMarketingSettingRequest $request): JsonResponse
    {
        $setting = MarketingSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $request->input('value'), 'description' => $request->input('description')]
        );

        return ApiResponse::success($setting, "Setting '{$key}' updated successfully");
    }
}
