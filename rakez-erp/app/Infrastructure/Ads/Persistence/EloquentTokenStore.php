<?php

namespace App\Infrastructure\Ads\Persistence;

use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use Illuminate\Support\Facades\Http;

final class EloquentTokenStore implements TokenStorePort
{
    public function getAccessToken(Platform $platform, string $accountId): ?string
    {
        $account = $this->findAccount($platform, $accountId);
        if (! $account) {
            return null;
        }

        if ($account->token_expires_at && $account->token_expires_at->isPast()) {
            return $this->refreshAccessToken($platform, $accountId);
        }

        return $account->access_token;
    }

    public function getRefreshToken(Platform $platform, string $accountId): ?string
    {
        return $this->findAccount($platform, $accountId)?->refresh_token;
    }

    public function saveTokens(
        Platform $platform,
        string $accountId,
        string $accessToken,
        ?string $refreshToken = null,
        ?\DateTimeInterface $expiresAt = null,
    ): void {
        AdsPlatformAccount::updateOrCreate(
            ['platform' => $platform->value, 'account_id' => $accountId],
            array_filter([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $expiresAt,
            ], fn ($v) => $v !== null),
        );
    }

    public function refreshAccessToken(Platform $platform, string $accountId): string
    {
        return match ($platform) {
            Platform::Snap => $this->refreshSnap($accountId),
            Platform::TikTok => $this->refreshTikTok($accountId),
            Platform::Meta => $this->getMetaToken($accountId),
        };
    }

    private function refreshSnap(string $accountId): string
    {
        $account = $this->findAccount(Platform::Snap, $accountId);
        if (! $account) {
            throw new \RuntimeException("Snap account not found for token refresh: {$accountId}");
        }
        if (empty($account->refresh_token)) {
            throw new \RuntimeException("Snap refresh_token is missing for account: {$accountId}");
        }

        $cfg = config('ads_platforms.snap');
        if (empty($cfg['client_id']) || empty($cfg['client_secret']) || empty($cfg['auth_url'])) {
            throw new \RuntimeException('Snap OAuth configuration is missing (client_id/client_secret/auth_url).');
        }

        $response = Http::asForm()->post($cfg['auth_url'], [
            'grant_type' => 'refresh_token',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $account->refresh_token,
        ])->throw()->json();

        $expiresAt = now()->addSeconds($response['expires_in'] ?? 3600);

        $this->saveTokens(
            Platform::Snap,
            $accountId,
            $response['access_token'],
            $response['refresh_token'] ?? $account->refresh_token,
            $expiresAt,
        );

        return $response['access_token'];
    }

    private function refreshTikTok(string $accountId): string
    {
        $account = $this->findAccount(Platform::TikTok, $accountId);
        if (! $account) {
            throw new \RuntimeException("TikTok account not found for token refresh: {$accountId}");
        }
        if (empty($account->refresh_token)) {
            throw new \RuntimeException("TikTok refresh_token is missing for account: {$accountId}");
        }

        $cfg = config('ads_platforms.tiktok');
        if (empty($cfg['app_id']) || empty($cfg['app_secret']) || empty($cfg['auth_url'])) {
            throw new \RuntimeException('TikTok OAuth configuration is missing (app_id/app_secret/auth_url).');
        }

        $response = Http::post($cfg['auth_url'], [
            'grant_type' => 'refresh_token',
            'client_key' => $cfg['app_id'],
            'client_secret' => $cfg['app_secret'],
            'refresh_token' => $account->refresh_token,
        ])->throw()->json();

        $expiresAt = now()->addSeconds($response['expires_in'] ?? 86400);

        $this->saveTokens(
            Platform::TikTok,
            $accountId,
            $response['access_token'],
            $response['refresh_token'] ?? $account->refresh_token,
            $expiresAt,
        );

        return $response['access_token'];
    }

    /**
     * Meta uses System User tokens which are non-expiring.
     */
    private function getMetaToken(string $accountId): string
    {
        $account = $this->findAccount(Platform::Meta, $accountId);

        return $account->access_token ?? config('ads_platforms.meta.access_token');
    }

    private function findAccount(Platform $platform, string $accountId): ?AdsPlatformAccount
    {
        return AdsPlatformAccount::where('platform', $platform->value)
            ->where('account_id', $accountId)
            ->first();
    }
}
