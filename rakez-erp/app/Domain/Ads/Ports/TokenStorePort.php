<?php

namespace App\Domain\Ads\Ports;

use App\Domain\Ads\ValueObjects\Platform;

interface TokenStorePort
{
    public function getAccessToken(Platform $platform, string $accountId): ?string;

    public function getRefreshToken(Platform $platform, string $accountId): ?string;

    public function saveTokens(
        Platform $platform,
        string $accountId,
        string $accessToken,
        ?string $refreshToken = null,
        ?\DateTimeInterface $expiresAt = null,
    ): void;

    /**
     * Refresh the access token using the stored refresh token.
     * Returns the new access token.
     */
    public function refreshAccessToken(Platform $platform, string $accountId): string;
}
