<?php

namespace App\Http\Controllers\Ads;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Meta\MetaClient;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Infrastructure\Ads\Snap\SnapClient;
use App\Infrastructure\Ads\TikTok\TikTokClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdsAccountsController
{
    /**
     * POST /api/ads/accounts
     * Create or update an Ads platform account in a production-safe (non-manual DB) way.
     */
    public function upsert(Request $request, TokenStorePort $tokenStore): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:meta,snap,tiktok',
            'account_id' => 'required|string|max:120',
            'account_name' => 'nullable|string|max:255',
            'access_token' => 'nullable|string',
            'refresh_token' => 'nullable|string',
            'token_expires_at' => 'nullable|date',
            'scopes' => 'nullable|array',
            'meta' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $platform = Platform::from($validated['platform']);
        $accountId = $validated['account_id'];

        $account = AdsPlatformAccount::updateOrCreate(
            ['platform' => $platform->value, 'account_id' => $accountId],
            array_filter([
                'account_name' => $validated['account_name'] ?? null,
                'scopes' => $validated['scopes'] ?? null,
                'meta' => $validated['meta'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ], fn ($v) => $v !== null),
        );

        if (! empty($validated['access_token'])) {
            $tokenStore->saveTokens(
                $platform,
                $accountId,
                $validated['access_token'],
                $validated['refresh_token'] ?? null,
                isset($validated['token_expires_at']) ? \Carbon\CarbonImmutable::parse($validated['token_expires_at']) : null,
            );
        }

        return response()->json([
            'id' => $account->id,
            'platform' => $account->platform,
            'account_id' => $account->account_id,
            'account_name' => $account->account_name,
            'token_expires_at' => $account->token_expires_at,
            'is_active' => $account->is_active,
        ], 201);
    }

    /**
     * PATCH /api/ads/accounts/{id}
     */
    public function update(int $id, Request $request, TokenStorePort $tokenStore): JsonResponse
    {
        $account = AdsPlatformAccount::findOrFail($id);

        $validated = $request->validate([
            'account_name' => 'nullable|string|max:255',
            'access_token' => 'nullable|string',
            'refresh_token' => 'nullable|string',
            'token_expires_at' => 'nullable|date',
            'scopes' => 'nullable|array',
            'meta' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $account->fill(array_filter([
            'account_name' => $validated['account_name'] ?? null,
            'scopes' => $validated['scopes'] ?? null,
            'meta' => $validated['meta'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'token_expires_at' => isset($validated['token_expires_at']) ? \Carbon\CarbonImmutable::parse($validated['token_expires_at']) : null,
        ], fn ($v) => $v !== null));
        $account->save();

        if (! empty($validated['access_token'])) {
            $tokenStore->saveTokens(
                Platform::from($account->platform),
                $account->account_id,
                $validated['access_token'],
                $validated['refresh_token'] ?? null,
                isset($validated['token_expires_at']) ? \Carbon\CarbonImmutable::parse($validated['token_expires_at']) : null,
            );
        }

        return response()->json([
            'id' => $account->id,
            'platform' => $account->platform,
            'account_id' => $account->account_id,
            'account_name' => $account->account_name,
            'token_expires_at' => $account->token_expires_at,
            'is_active' => $account->is_active,
        ]);
    }

    /**
     * POST /api/ads/accounts/{id}/refresh
     * Refresh access token for providers that support refresh tokens (Snap/TikTok).
     */
    public function refresh(int $id, TokenStorePort $tokenStore): JsonResponse
    {
        $account = AdsPlatformAccount::findOrFail($id);
        $platform = Platform::from($account->platform);

        $newToken = $tokenStore->refreshAccessToken($platform, $account->account_id);

        $account->refresh();

        return response()->json([
            'platform' => $account->platform,
            'account_id' => $account->account_id,
            'token_expires_at' => $account->token_expires_at,
            'access_token_prefix' => substr($newToken, 0, 6) . '...',
        ]);
    }

    /**
     * POST /api/ads/accounts/{id}/test
     * Validate provider connectivity and token correctness using minimal provider calls.
     */
    public function test(int $id, MetaClient $meta, SnapClient $snap, TikTokClient $tiktok): JsonResponse
    {
        $account = AdsPlatformAccount::findOrFail($id);
        $platform = Platform::from($account->platform);

        $result = match ($platform) {
            Platform::Meta => $meta->get($account->account_id, ['fields' => 'id,name'], $account->account_id),
            Platform::Snap => $snap->get("adaccounts/{$account->account_id}", [], $account->account_id),
            Platform::TikTok => $tiktok->get('campaign/get/', ['advertiser_id' => $account->account_id, 'page' => 1, 'page_size' => 1], $account->account_id),
        };

        return response()->json([
            'success' => true,
            'platform' => $account->platform,
            'account_id' => $account->account_id,
            'result' => $result,
        ]);
    }
}
